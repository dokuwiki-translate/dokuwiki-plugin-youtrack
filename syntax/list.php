<?php
/**
 * DokuWiki Plugin youtrack (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author  Anika Henke <anika@zopa.com>
 */

// must be run within Dokuwiki
if (!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');

class syntax_plugin_youtrack_list extends DokuWiki_Syntax_Plugin {
    /**
     * @return string Syntax mode type
     */
    public function getType() {
        return 'substition';
    }
    /**
     * @return string Paragraph type
     */
    public function getPType() {
        return 'block';
    }
    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort() {
        return 200;
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('{{youtrack-list>.*?}}',$mode,'plugin_youtrack_list');
    }

    /**
     * Handle matches of the youtrack syntax
     *
     * @param string $match The match of the syntax
     * @param int    $state The state of the handler
     * @param int    $pos The position in the document
     * @param Doku_Handler    $handler The handler
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler &$handler){
        $ytissues = array();
        $issues = array();

        list($tmp, $match) = explode('>', substr($match, 0, -2), 2); // strip markup
        list($filter, $cols) = explode('|', $match, 2); // split filter and columns from rest of match
        if (empty($filter) || empty($cols)) return false;
        $cols = array_map('trim', explode(',', $cols));

        $yt = $this->loadHelper('youtrack');

        if($yt) {
            $ytissues = $yt->getIssues($filter);
        }
        if ($ytissues === false) {
            return false;
        }

        foreach ($ytissues as $issue) {
            $issueData = array();

            foreach ($cols as $col) {
                $fieldFound = false;
                $id = (string) $issue->attributes()->id;

                foreach($issue as $field) {
                    if ($field->attributes()->name == $col) {
                        $fieldFound = true;
                        $value = (string) $field->value;
                        $fullname = (string) $field->value->attributes()->fullName;

                        // if 13 digit number, very likely to be date (timestamp in milliseconds)
                        if(preg_match('/^\d{13}$/', $value)) {
                            $issueData[$col] = date($this->getConf('date_format'), $value/1000);
                        // if a field has a fullName attribute, it is better to use it
                        } elseif($fullname) {
                            $issueData[$col] = $fullname;
                        } else {
                            $issueData[$col] = $value;
                        }
                    }
                }

                if ($col == 'ID') {
                    $issueData[$col] = $id;
                } elseif (!$fieldFound) {
                    msg('Field "'.$col.'" not found for issue "'.$id.'"', -1);
                }
            }

            $issues[] = $issueData;
        }

        return array($issues, $cols);
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string         $mode      Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer  $renderer  The renderer
     * @param array          $data      The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer &$renderer, $data) {
        list($issues, $cols) = $data;

        if (count($issues) == 0) {
            global $lang;
            $renderer->p_open();
            $renderer->cdata($lang['nothingfound']);
            $renderer->p_close();
            return true;
        }

        $yt = $this->loadHelper('youtrack');
        $yt->renderIssueTable($renderer, $issues, $cols);

        return true;
    }
}

// vim:ts=4:sw=4:et:
