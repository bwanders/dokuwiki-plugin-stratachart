<?php
/**
 * Strata Chart, pie chart plugin
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Brend Wanders <b.wanders@utwente.nl>
 */
 
if (!defined('DOKU_INC')) die('Meh.');

class syntax_plugin_stratachart extends syntax_plugin_strata_select {
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<chart:pie(?: (?:left|right))?'.$this->helper->fieldsShortPattern().'* *>\s*?\n.+?\n\s*?</chart>',$mode, 'plugin_stratachart');
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

        // create empty group so we can always grab settings (and use defaults if needed)
        if(empty($cs)) $cs = array();

        // parse settings
        $config = array(
            'width'  => array('pattern'=>'/^[0-9]+$/', 'default'=>'400'),
            'height' => array('pattern'=>'/^[0-9]+$/', 'default'=>'300'),
            'legend' => array('choices'=>array('on'=>array('on','true'), 'off'=>array('off','false')), 'default'=>'on'),
            'sort'   => array('choices'=>array('on'=>array('on','true'), 'off'=>array('off','false')), 'default'=>'on'),
            'significance' => array('pattern'=>'/^([0-9]+)|(detect)$/', 'default'=>'detect')
        );

        $settings = $this->helper->setProperties($config, $cs);
        $result['chart'] = array_merge($result['chart'], array_map(function($x) { return $x[0]; }, $settings));

        // convert on/off settings to booleans
        foreach(array('legend', 'sort') as $key) {
            $result['chart'][$key] = $result['chart'][$key] == 'on';
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
                'type'=>$this->util->loadType($meta['type']),
                'typeName'=>$meta['type'],
                'hint'=>$meta['hint'],
                'aggregate'=>$this->util->loadAggregate($meta['aggregate']),
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
            if($data['chart']['significance'] == 'detect') {
                $significance = -1;
                foreach($pairs as $entry) {
                    list($key, $value) = $entry;

                    $significance = max(
                        $significance, 
                        ($value - floor($value) == 0) ? 0 : strlen(strval($value-floor($value)))-2
                    );
                }
            } else {
                $significance = $data['chart']['significance'];
            }

            // create pairs
            foreach($pairs as $p) {
                list($key,$value) = $p;
                $dx[] = array('label'=>$key, 'data'=>(0+$value));
            }

            // aligns the image (left, right or empty string)
            $align = $data['chart']['align'];

            $slices = json_encode($dx, JSON_HEX_APOS);

            $options = json_encode(array(
                'legend'=>$data['chart']['legend'],
                'significance'=>$significance,
                'strokeColor'=>$this->getConf('background_colour'),
            ), JSON_HEX_APOS);

            $R->doc .= '<div style="width:'.$data['chart']['width'].'px;height:'.$data['chart']['height'].'px;"';
            $R->doc .= ' class="stratachart stratachart_pie media'.$align.'"';
            $R->doc .= ' data-pie=\''.$slices.'\' data-options=\''.$options.'\'';
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
