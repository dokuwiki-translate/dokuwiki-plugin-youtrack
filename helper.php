<?php
/**
 * DokuWiki Plugin youtrack (Helper Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Dominic Pöllath <dominic.poellath@ils-gmbh.net>
 * @author  Anika Henke <anika@zopa.com>
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

class helper_plugin_youtrack extends DokuWiki_Plugin {

    protected $cookie = false;

    /**
     * Return info about supported methods in this Helper Plugin
     *
     * @return array of public methods
     */
    public function getMethods() {
        return array(
            array(
                'name'   => 'request',
                'desc'   => 'Send request to REST API',
                'params' => array(
                    'method'            => 'string',
                    'endpoint'          => 'string',
                    'params (optional)' => 'array'
                ),
                'return' => array('Response' => 'mixed')
            ),
            array(
                'name'   => 'getBaseUrl',
                'desc'   => 'Get YouTrack base URL',
                'return' => array('URL' => 'string')
            ),
            array(
                'name'   => 'login',
                'desc'   => 'Login to YouTrack',
                'return' => array('If logged in or not' => 'bool')
            ),
            array(
                'name'   => 'logout',
                'desc'   => 'Logout of YouTrack by deleting cookie',
            ),
            array(
                'name'   => 'getIssue',
                'desc'   => 'Get issue by ID',
                'params' => array(
                    'id' => 'string',
                ),
                'return' => array('Issue data' => 'SimpleXMLElement')
            ),
            array(
                'name'   => 'getIssues',
                'desc'   => 'Get issues by filter',
                'params' => array(
                    'filter' => 'string',
                ),
                'return' => array('Issues data' => 'SimpleXMLElement')
            ),
            array(
                'name'   => 'getIssueUrl',
                'desc'   => 'Get issues by filter',
                'params' => array(
                    'id' => 'string',
                ),
                'return' => array('URL' => 'string')
            ),
            array(
                'name'   => 'renderIssueTable',
                'desc'   => 'Render table of issues',
                'params' => array(
                    'R'      => 'Doku_Renderer',
                    'issues' => 'string',
                    'cols'   => 'string'
                ),
            ),
        );
    }

    /**
     * Send request to REST API
     *
     * @param string $method    HTTP methods: POST, PUT, GET etc
     * @param string $endpoint  API endpoint
     * @param array  $params    Request params: array("param" => "value") ==> ?param=value
     * @return SimpleXMLElement Response
     */
    function request($method, $endpoint, $params = false) {
        if (!function_exists('curl_init')) {
            msg('You have to install curl first to use the YouTrack plugin', -1);
            return false;
        }

        $url = $this->getBaseUrl().$endpoint;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($curl, CURLOPT_TIMEOUT, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

        switch ($method) {
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);
                if ($params) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
                }
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_PUT, 1);
                break;
            default:
                if ($params) {
                    $url = sprintf("%s?%s", $url, http_build_query($params));
                }
        }

        curl_setopt($curl, CURLOPT_URL, $url);

        // Optional Authentication:
        // curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        // curl_setopt($curl, CURLOPT_USERPWD, "username:password");
        // curl_setopt($curl, CURLOPT_COOKIEJAR,  $ckfile);
        if ($this->cookie) {
            curl_setopt($curl, CURLOPT_COOKIEFILE, $this->cookie);
            curl_setopt($curl, CURLOPT_COOKIEJAR, $this->cookie);
        }

        $content = curl_exec($curl);
        curl_close($curl);
        return $content ? $content : null;
    }


    /**
     * Get YouTrack base URL (without ending slash)
     *
     * @return string URL
     */
    function getBaseUrl() {
        $url = $this->getConf('url');
        if (empty($url)) {
            msg('YouTrack URL is not defined.', -1);
        }
        return substr($url, -1) === '/' ? substr($url, 0, strlen($url)-1) : $url;
    }


    /**
     * Login to YouTrack
     *
     * @return bool If logged in or not
     */
    function login() {
        $user = $this->getConf('user');
        $password = $this->getConf('password');
        $url = $this->getBaseUrl();

        if (empty($user) || empty($password) || empty($url)) {
            $this->cookie = false;
            return false;
        }

        $this->cookie = tempnam(sys_get_temp_dir(), "CURLCOOKIE");

        $content = $this->request(
            "POST",
            "/rest/user/login",
            array(
                "login" => $user,
                "password" => $password
            )
        );

        if ($content && simplexml_load_string($content) != "ok") {
            msg('Login data not correct, or REST login is not enabled.', -1);
            return false;
        }
        return true;
    }

    /**
     * Logout of YouTrack by deleting cookie
     */
    function logout() {
        if ($this->cookie) {
            unlink($this->cookie) or die("Can't unlink $this->cookie");
        }
    }

    /**
     * Get issue by ID
     *
     * @param string $id ID of issue
     * @return SimpleXMLElement Issue data
     */
    function getIssue($id) {
        $this->login();
        $xml = $this->request("GET", "/rest/issue/$id");
        $this->logout();
        return simplexml_load_string($xml);
    }

    /**
     * Get issues by filter
     *
     * @param string $filter Filter in YouTrack query language
     * @return SimpleXMLElement Issues data
     */
    function getIssues($filter) {
        $this->login();
        $xml = $this->request(
            "GET",
            "/rest/issue/",
            array(
                'filter' => $filter,
                'max' => 100 // TODO: set max via config?
            )
        );
        $this->logout();
        return simplexml_load_string($xml);
    }

    /**
     * Get link to issue
     *
     * @param string $id ID of issue
     * @return string
     */
    function getIssueUrl($id) {
        if (empty($id)) {
            return '';
        }
        return $this->getBaseUrl().'/issue/'.$id;
    }

    /**
     * Render table of issues
     *
     * @param Doku_Renderer $R      Renderer
     * @param array         $issues Issues with their relevant values
     * @param array         $cols   Columns to show
     */
    function renderIssueTable(Doku_Renderer &$R, $issues, $cols) {
        $R->table_open(count($cols));
            $R->tablethead_open();
                $R->tablerow_open();
                    foreach($cols as $col) {
                        $R->tableheader_open();
                        $R->cdata($col);
                        $R->tableheader_close();
                    }
                $R->tablerow_close();
            $R->tablethead_close();
            $R->tabletbody_open();

                foreach ($issues as $issue) {
                    $R->tablerow_open();
                        foreach($cols as $col) {
                            $R->tablecell_open();
                            if ($col == 'ID') {
                                $R->externallink($this->getIssueUrl($issue[$col]), $issue[$col]);
                            } else {
                                $R->cdata($issue[$col]);
                            }
                            $R->tablecell_close();
                        }
                    $R->tablerow_close();
                }

            $R->tabletbody_close();
        $R->table_close();
    }

}
// vim:ts=4:sw=4:et:
