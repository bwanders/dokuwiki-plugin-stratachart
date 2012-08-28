<?php
/**
 * Strata Chart, pie chart plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Brend Wanders <b.wanders@utwente.nl>
 */
 
if (!defined('DOKU_INC')) die('Meh.');

class syntax_plugin_stratachart extends syntax_plugin_stratabasic_select {
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<chart:pie (?:left|right)?'.$this->helper->fieldsShortPattern().'* *>\s*?\n.+?\n\s*?</chart>',$mode, 'plugin_stratachart');
    }

    function handleHeader($header, &$result, &$typemap) {
        $result['chart'] = array();
        preg_match('/(?:^<chart:pie (left|right))|(?: *>$)/',$header,$m);
        $result['chart']['align'] = $m[1];

        return preg_replace('/(^<chart:pie)|( *>$)/','',$header);
    }

    function handleBody(&$tree, &$result, &$typemap) {
        if(count($result['fields']) != 2) {
            throw new stratabasic_exception($this->getLang('error_bad_fields'),array());
        }
        
        $result['chart']['width'] = 400;
        $result['chart']['height'] = 300;
        $result['chart']['legend'] = true;
        $result['chart']['sort'] = true;
        $result['chart']['significance'] = -1;

        $cs = $this->helper->extractGroups($tree, 'chart');
        if(count($cs) > 1) throw new stratabasic_exception($this->getLang('error_too_many_settings'), $cs);
        if(count($cs)) {
            $ts = $this->helper->extractText($cs[0]);
            foreach($ts as $lineNode) {
                $line = $lineNode['text'];
                list($key, $value) = explode(':',$line);
                $key = trim($key); $value=trim($value);
                switch($key) {
                    case 'width': $result['chart']['width'] = intval($value); break;
                    case 'height': $result['chart']['height'] = intval($value); break;
                    case 'legend': $result['chart']['legend'] = $value=='on'; break;
                    case 'sort': $result['chart']['sort'] = $value=='on'; break;
                    case 'significance': $result['chart']['significance'] = intval($value); break;
                    default: throw new stratabasic_exception($this->getLang('error_unknown_setting'), array($lineNode));
                }
            }
        }
    }

    function render($mode, &$R, $data) {
        if($data == array() || isset($data['error'])) {
            if($mode == 'xhtml') {
                $R->table_open();
                $R->tablerow_open();
                $R->tablecell_open();
                $this->displayError($R, $data);
                $R->tablecell_close();
                $R->tablerow_close();
                $R->table_close();
            }
            return;
        }


        $query = $this->prepareQuery($data['query']);

        // execute the query
        $result = $this->triples->queryRelations($query);

        // prepare all 'columns'
        $fields = array();
        foreach($data['fields'] as $meta) {
            $fields[] = array(
                'variable'=>$meta['variable'],
                'type'=>$this->types->loadType($meta['type']),
                'typeName'=>$meta['type'],
                'hint'=>$meta['hint'],
                'aggregate'=>$this->types->loadAggregate($meta['aggregate']),
                'aggergateHint'=>$meta['aggregateHint']
            );
        }

        if($mode == 'xhtml') {
            // storage for pie slices
            $pairs = array();

            // process the results
            foreach($result as $row) {
                // the keys in the first field
                $keys = array();

                // fetch data from row (only using the key and value fields)
                foreach(array(0,1) as $fi) {
                    $f = $fields[$fi];
                    $values = $f['aggregate']->aggregate($row[$f['variable']], $f['aggregateHint']);
                    if(!count($values)) continue;
                    foreach($values as $value) {
                        if($fi == 0) {
                            // assign keys for the first field
                            $keys[] = $value;
                        } else {
                            // create the cartesian product of values for the second
                            foreach($keys as $k) $pairs[] = array($k,$value);
                        }
                    }
                }
            }
            $result->closeCursor();

            $dx = array();

            // sort largest to smallest
            if($data['chart']['sort']) {
                usort($pairs, function($a, $b) {
                    if($a[1] == $b[1]) return 0;
                    if($a[1] > $b[1]) return -1;
                    if($a[1] < $b[1]) return 1;
                });
            }

            // auto-detect significance
            if($data['chart']['significance'] < 0) {
                foreach($pairs as $entry) {
                    list($key, $value) = $entry;
                    $data['chart']['significance'] = max(
                        $data['chart']['significance'], 
                        strlen(strval($value-floor($value)))-2
                    );
                }
            }

            // create pairs
            foreach($pairs as $p) {
                list($key,$value) = $p;
                $dx[] = array('label'=>$key, 'data'=>(0+$value));
            }

            // aligns the image (left, right or empty string)
            $align = $data['chart']['align'];

            $slices = json_encode($dx, JSON_HEX_APOS);

            $R->doc .= '<div style="width:'.$data['chart']['width'].'px;height:'.$data['chart']['height'].'px;"';
            $R->doc .= ' class="stratachart stratachart_pie media'.$align.'"';
            $R->doc .= ' data-pie=\''.$slices.'\' data-significance="'.$data['chart']['significance'].'" data-legend="'.$data['chart']['legend'].'"';
            // make left/right alignment for no-CSS view work (feeds)
            if($align == 'right') $ret .= ' align="right"';
            if($align == 'left')  $ret .= ' align="left"';
            $R->doc .= '></div>';

            return true;
        } elseif($mode == 'metadata') {
            // render all rows in metadata mode to enable things like backlinks
            foreach($result as $row) {
                foreach($fields as $f) {
                    foreach($f['aggregate']->aggregate($row[$f['variable']],$f['aggregateHint']) as $value) {
                        $f['type']->render($mode, $R, $this->triples, $value, $f['hint']);
                    }
                }
            }
            $result->closeCursor();

            return true;
        }

        return false;
    }
}
