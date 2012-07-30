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

        $cs = $this->helper->extractGroups($tree, 'chart');
        if(count($cs) > 1) throw new stratabasic_exception($this->getLang('error_too_many_settings'), $cs);
        $ts = $this->helper->extractText($cs[0]);
        foreach($ts as $lineNode) {
            $line = $lineNode['text'];
            list($key, $value) = explode(':',$line);
            $key = trim($key); $value=trim($value);
            switch($key) {
                case 'width': $result['chart']['width'] = intval($value); break;
                case 'height': $result['chart']['height'] = intval($value); break;
                case 'legend': $result['chart']['legend'] = $value=='on'; break;
                default: throw new stratabasic_exception($this->getLang('error_unknown_setting'), array($lineNode));
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
            foreach($pairs as $p) {
                list($key,$value) = $p;
                $dx[]=str_replace('|',' ',$key);
                $dx[]=str_replace('|',' ',$value);
            }

            // aligns the image (left, right or empty string)
            $align = $data['chart']['align'];

            $params = array();
            $params['d'] = implode('|', $dx);

            // pass colors
            $params['legend-background'] = $this->getConf('legend_background');
            $params['legend-color'] = $this->getConf('legend_color');
            $params['legend-border'] = $this->getConf('legend_border');
            $params['background'] = $this->getConf('background');

            // pass optional settings
            if(isset($data['chart']['width'])) $params['w'] = $data['chart']['width'];
            if(isset($data['chart']['height'])) $params['h'] = $data['chart']['height'];
            if($this->getConf('antialias')) $params['aa'] = 'on';
            if(isset($data['chart']['legend']) && !$data['chart']['legend']) $params['legend'] = 'off';

            $url = DOKU_BASE.'lib/plugins/stratachart/chart.php?'.buildURLparams($params);

            $R->doc .= '<img src="'.$url.'"';
            $R->doc .= ' class="media'.$align.'"';
            // make left/right alignment for no-CSS view work (feeds)
            if($align == 'right') $ret .= ' align="right"';
            if($align == 'left')  $ret .= ' align="left"';
            $R->doc .= ' />';

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
