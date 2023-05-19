<?php

if (!defined('DOKU_INC')) die();

include_once 'PlantUmlDiagram.php';

class syntax_plugin_plantumlparser_injector extends DokuWiki_Syntax_Plugin {
    private $TAG = 'uml';

    /**
     * @return string Syntax mode type
     */
    public function getType() {
        return 'substition';
    }
    /**
     * @return int Sort order - Low numbers go before high numbers
     */
    public function getSort() {
        return 199; // In case we are operating in a Dokuwiki that has the other PlantUML plugin we want to beat it.
    }

    /**
     * Connect lookup pattern to lexer.
     *
     * @param string $mode Parser mode
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('<'.$this->TAG.'>\n*.*?\n*</'.$this->TAG.'>',$mode,'plugin_plantumlparser_injector');
    }

    /**
     * Handle matches of the plantumlparser syntax
     *
     * @param string          $match   The match of the syntax
     * @param int             $state   The state of the handler
     * @param int             $pos     The position in the document
     * @param Doku_Handler    $handler The handler
     * @return array Data for the renderer
     */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        $markup        = str_replace('</' . $this->TAG . '>', '', str_replace('<' . $this->TAG . '>', '', $match));
    
        // search links in DokuWiki syntax
        preg_match_all('/\[\[([^\|]*)\|([^\]]*)\]\]/m', $markup, $all_dwlinks, PREG_SET_ORDER);

        // rewrite URL-Syntax: DokuWiki -> PlantUML
        // build URL-List in DokuWiki syntax for metadata (e.q. for backlinks)
        $dokuwiki_links = array();
        foreach ($all_dwlinks as $dwlink) {
            if (preg_match('|^[^:]+//|', $dwlink[1])) {
                // external Link
                $markup = str_replace($dwlink[0], "[[$dwlink[1] $dwlink[2]]]", $markup);
            } else {
                // internal Link, add DokuWiki call
                $markup = str_replace($dwlink[0], "[[/doku.php?id=$dwlink[1] $dwlink[2]]]", $markup);
                $dokuwiki_links [] = [
                        'page' => $dwlink[1],
                        'title' => $dwlink[2],
                ];
            }
        }
        unset($dwlink);        // search links in DokuWiki syntax
        
        $plantUmlUrl   = trim($this->getConf('PlantUMLURL'));
        if(!$plantUmlUrl)
        {
            $plantUmlUrl = "https://www.plantuml.com/plantuml/";
        }
        else
        {
            $plantUmlUrl = trim($plantUmlUrl, '/') . '/';
        }
        $diagramObject = new PlantUmlDiagram($markup,$plantUmlUrl);

        return [
            'svg' => strstr($diagramObject->getSVG(), "<svg"),
            'markup' => $diagramObject->getMarkup(),
            'id' => sha1($diagramObject->getSVGDiagramUrl()),
            'include_links' => $this->getConf('DefaultShowLinks'),
            'url' => [
                'svg' => $diagramObject->getSVGDiagramUrl(),
                'png' => $diagramObject->getPNGDiagramUrl(),
                'txt' => $diagramObject->getTXTDiagramUrl(),
            ],
            'dokuwiki_links' => $dokuwiki_links,
        ];
    }

    /**
     * Render xhtml output or metadata
     *
     * @param string         $mode      Renderer mode (supported modes: xhtml)
     * @param Doku_Renderer  $renderer  The renderer
     * @param array          $data      The data from the handler() function
     * @return bool If rendering was successful.
     */
    public function render($mode, Doku_Renderer $renderer, $data) {
        if($mode == 'metadata') {
            foreach ($data['dokuwiki_links'] as $dwlink) {
                $renderer->internallink($dwlink['page'], $dwlink['title']);
            }
            return true;
        }

        if($mode != 'xhtml') return false;

        $renderer->doc .= "<div id='plant-uml-diagram-".$data['id']."'>";
        if(strlen($data['svg']) > 0) {
            if(is_a($renderer,'renderer_plugin_dw2pdf') && (preg_match("/(@startlatex|@startmath|<math|<latex)/", $data['markup']))){
                $renderer->doc .= "<img src='".$data['url']['png']."'>";
            }
            else {
                $renderer->doc .= $data['svg'];
            }
        } else {
            $renderer->doc .= "<object data='".$data['url']['svg']."' type='image/svg+xml'>";
            $renderer->doc .= "<span>".$data['markup']."</span>";
            $renderer->doc .= "</object>";
        }
        if($data['include_links'] == "1") {
            $renderer->doc .= "<div id=\"plantumlparse_link_section\">";
            $renderer->doc .= "<a target='_blank' href='".$data['url']['svg']."'>SVG</a> | ";
            $renderer->doc .= "<a target='_blank' href='".$data['url']['png']."'>PNG</a> | ";
            $renderer->doc .= "<a target='_blank' href='".$data['url']['txt']."'>TXT</a>";
            $renderer->doc .= "</div>";
        }
        
        $renderer->doc .= "</div>";

        return true;
    }
}
