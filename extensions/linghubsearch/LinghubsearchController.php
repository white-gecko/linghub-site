<?php
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2014, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * Search component controller.
 * 
 * @category   OntoWiki
 * @package    Extensions_linghubsearch
 * @author     Henri Knochenhauer <hknochi@gmail.com>
 * @copyright  Copyright (c) 2014, {@link http://aksw.org AKSW}
 * @license    http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */
class LinghubsearchController extends OntoWiki_Controller_Component
{
    public function init()
    {
        parent::init();
    }

    public function fulltextAction(){

        // tells the OntoWiki to not apply the template to this action
        $this->_helper->viewRenderer->setNoRender();
        $this->_helper->layout->disableLayout();

        $store = $this->_erfurt->getStore();
        $this->_erfurt->authenticate();

        if ($this->_request->query !== null) {
            $searchText = htmlspecialchars(trim($this->_request->query));
        }

        $error    = false;
        $errorMsg = '';

        // check if search is already errorenous
        if (!$error) {

            // try sparql query pre search check (with limit to 1)
            $model = $this->_owApp->selectedModel;
            $modelUri = $this->_owApp->selectedModel->getModelIri();

            $elements = $store->getSearchPattern($searchText, $modelUri);

            $query = new Erfurt_Sparql_Query2();
            $query->addElements($elements);

            $resourceUri= $query->getVar('resourceUri');
            $voidPrefix = new Erfurt_Sparql_Query2_Prefix('void', new Erfurt_Sparql_Query2_IriRef('http://rdfs.org/ns/void#'));

            $triple = new Erfurt_Sparql_Query2_Triple($resourceUri, new Erfurt_Sparql_Query2_A(), new Erfurt_Sparql_Query2_IriRef('Dataset', $voidPrefix));
            $triple2 = new Erfurt_Sparql_Query2_Triple(new Erfurt_Sparql_Query2_Var('resourceUri'), new Erfurt_Sparql_Query2_Var('p'), new Erfurt_Sparql_Query2_Var('o'));

            $bifPrefix = new Erfurt_Sparql_Query2_Prefix('bif', new Erfurt_Sparql_Query2_IriRef('SparqlProcessorShouldKnow'));
            $bifContains = new Erfurt_Sparql_Query2_IriRef('contains', $bifPrefix);
            $filter =
                new Erfurt_Sparql_Query2_ConditionalOrExpression(
                    array(
                        /*new Erfurt_Sparql_Query2_Function(
                            $bifContains,
                            array($subjectVariable, new Erfurt_Sparql_Query2_RDFLiteral($stringSpec))
                        ),
                        // why doesnt this work???
                        // ANSWER: bif:contains uses virtuoso specific fulltext index only
                        // available for object column uris could only be treated as codepoint representation
                        // of themselves -> Solution again is IRI (maybe not before PHP 6)
                         */
                        new Erfurt_Sparql_Query2_Function(
                            $bifContains,
                            array(new Erfurt_Sparql_Query2_Var('o'), new Erfurt_Sparql_Query2_RDFLiteral($searchText, null, '"\''))
                        )
                    )

            );

            $filter = new Erfurt_Sparql_Query2_Regex(new Erfurt_Sparql_Query2_Var('o'), new Erfurt_Sparql_Query2_RDFLiteral($searchText),new Erfurt_Sparql_Query2_RDFLiteral('i'));

            $ggp = new Erfurt_Sparql_Query2_GroupGraphPattern();
            $ggp->addElement($triple);
            $ggp->addElement($triple2);
            $ggp->addFilter($filter);

            $query->setLimit(11);
            $query->setDistinct(true);
            $query->setWhere($ggp);

            try {

                $result = $model->sparqlQuery($query); //, array('result_format' => 'extended'));

                $desc = array();
                foreach($result as $statement){
                    $desc = array_merge($desc, $model->getResource($statement['resourceUri'])->getDescription(1));
                }


            } catch (Exception $e) {

                // build error message
                $this->_owApp->appendMessage(
                    new OntoWiki_Message(
                        $this->_owApp->translate->_('search failed'),
                        OntoWiki_Message::ERROR
                    )
                );

                $error = true;
                $errorMsg .= 'Message details: ';
                $errorMsg .= str_replace('LIMIT 1', '', $e->getMessage());

            }

        }

        // if error occured set output for error page
        if ($error) {

            $this->view->errorMsg = $errorMsg;

        } else {
            // set redirect to effective search controller
            //$url = new OntoWiki_Url(array('controller' => 'list'), array());
            //$url->setParam('s', $searchText);
            //$url->setParam('init', '1');
            //$this->_redirect($url);

        }

        $this->_response->setBody(json_encode($desc));

    }
}
