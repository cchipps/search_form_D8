<?php

namespace Drupal\search_form\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @file
 * Contains docroot\modules\custom\search_form\src\Form\SearchForm.php
 */
class SearchForm extends FormBase {

  /**
   * The entity storage for items.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $searchDataStorage;

  /**
   * Constructs the  form object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->searchDataStorage = $entity_type_manager->getStorage('page');
  }
  
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }
  
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'search_form_form';
  }
  
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get the form_state variables.
    $results_arr = $form_state->get('results_arr', NULL);
    $result = $form_state->get('fff-result', NULL);
    $search = $form_state->get('fff-search', NULL);
    
    if (isset($search)) {
      $form['search'] = [
          '#type' => 'hidden',
          '#value' => $search, 
          '#attributes' => ['id' => 'fff-search'],
      ];
    }
    if (isset($result)) {
      $form['result'] = [
          '#type' => 'hidden',
          '#value' => $result,
          '#attributes' => ['id' => 'fff-result'],
      ];
    }
    
    // Form render array of results.
    if(isset($results_arr) && count($results_arr) > 0 ){
      $count = 1;
      $form['path']['#access'] = FALSE;
      $form['actions']['#type'] = 'actions';
      $form['wrapper'] = ['#type' => 'container', '#attributes' => ['class' => ['col-lg-12']], ];
      foreach ($results_arr as $idx => $resultCode){
              $form['wrapper']['result'][$idx]['link'] = ['#title' => $this->t($resultCode['title']) , '#type' => 'link', '#url' => url::fromUserInput($resultCode['url']) , '#prefix' => '<h3> No. ' . $count++ . ' ', '#suffix' => '</h3>', ];
      }
      $form['newButton'] = ['#title' => $this->t('New Search') , '#type' => 'link', '#url' => url::fromUserInput('/entity_search') , '#value' => $this->t('New Search') , '#attributes' => ['type' => 'reset', 'class' => ['fff-media-alert-btn', 'btn', 'btn-primary', 'new-search']], ];
      $form['resultsDiv'] = ['#type' => 'html_tag', '#tag' => 'div', '#attributes' => ['id' => 'multipleResults', ], ];
      // Clear the results array session variable
      $form_state->set('results_arr', NULL);
      $form_state->setRebuild();
    }else{
      $form['html_txt']['title'] = ['#type' => 'html_tag', '#tag' => 'h2', '#value' => $this->t('Instructions'), ];
      $form['html_txt']['intro'] = ['#type' => 'html_tag', '#tag' => 'ol', '#value' => $this->t('<li>Entner a page title here.</li>'), ];
      $form['wrapper'] = ['#type' => 'container', '#attributes' => ['id' => 'searchBox', 'class' => ['fff-search__compontent', 'col-lg-12']], ];
      $form['wrapper']['wrapperCol1'] = ['#type' => 'container', '#attributes' => ['class' => ['col-lg-12', 'fff-search__row-1']], ];
      $form['wrapper']['wrapperCol2'] = ['#type' => 'container', '#attributes' => ['class' => ['col-lg-12', 'fff-search__row-2']], ];
      $form['wrapper']['wrapperCol1']['fff_search_box'] = ['#type' => 'textarea', '#attributes' => ['class' => ['fff-search__area', 'form-control']], ];
      $form['actions']['#type'] = 'actions';
      $form['wrapper']['wrapperCol2']['actions']['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Search') ,
          '#button_type' => 'primary',
          '#attributes' => ['type' => 'submit',
                            'class' => ['col-lg-5',
                            'fff-search__submit-button',
                            'btn btn-primary']],
      ];
      $form['wrapper']['wrapperCol2']['actions']['reset'] = [
          '#type' => 'html_tag',
          '#tag' => 'button',
          '#value' => $this->t('Clear') ,
          '#attributes' => ['type' => 'reset',
              'id' => 'reset',
              'class' => ['col-lg-5',
                          'fff-search__clear-button',
                          'btn btn-primary']],
      ];
    }
   
    return $form;
  }
  
  
  /**
   * {@inheritdoc}
   */
  public function submitForm(array & $form, FormStateInterface $form_state) {
    $fArr = [];
    $aStr = $this->stripString($form_state->getValue('fff_search_box'));
    $nodes = $this->ruleDBLoad();
    if (isset($nodes) && count($nodes) > 0){
      //Conditional to grab nodes
      foreach ($nodes as $idx => $node){
          if(stristr($node->title->value, $aStr)){
            $fArr[] = [
                      'sort' => strlen($node->title->value),
                      'nid' => $node->nid->value,
                      'title' => $node->title->value,
                      'url' => Url::fromRoute('entity.node.canonical', ['node' => $node->nid->value])->toString(),
                      'group' => $idx,
                     ];
          }
      }
    }
    $fArr = array_map("unserialize", array_unique(array_map("serialize", $fArr))); //get uniques
    // Group together
    foreach ($fArr as $key => $item) {
      $results_arr[$key] = $item;
    }
    $form_state->set('results_arr', $results_arr);
    $form_state->setRebuild();
      }
  
  /**
   * {@inheritdoc}
   */
    public function submitFormReset($form, &$form_state) {
      $form_state->setRebuild();
      // Clear the results array session variable
      $form_state->set('results_arr', NULL);
    }
  
  /**
   * {@inheritdoc}
   */
  public function validateForm(array & $form, FormStateInterface $form_state) {
    $str = $this->stripString($form_state->getValue('fff_search_box'));
    if (strlen($str) <= 0) {
      $form_state->setErrorByName('fff_search_box', $str . ': ' . $this->t('Whoopsies!'));
      // Clear the results array session variable
      $form_state->set('results_arr', NULL);
    }
  }
  
  /**
   * {@inheritdoc}
   */
  private function stripString($str) {
    //strip vars
    setlocale(LC_ALL, 'en_US.UTF8');
    $delimiter = '-';
    $clean = trim(rawurldecode(strip_tags(html_entity_decode(filter_var($str, FILTER_SANITIZE_STRING)))));
    $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
    return $clean;
  }
  
  /**
   * {@inheritdoc}
   */
  private function ruleDBLoad() {
    //node load outside of conditional, we are going to loop twice
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $node_storage = $entity_type_manager->getStorage('node');
    $query = $node_storage->getQuery()
      ->condition('type', 'page')
      ->condition('status', 1);
    $results = $query->execute();
    return $nodes = $node_storage->loadMultiple($results);
  }
  
  /**
   * Capture search data
   *
   * @param string $label
   *   The event label as sent to GA
   * @param string $path_name
   *   The path to the result page
   * @param string $search_query
   *   The search query entered in the FFFF search box
   */
  private function saveSearchData($label, $path_name, $search_query) {
    $values = [
      'event_category' => 'Search Results',
      'event_label' => $label,
      'event_action' => $path_name,
      'search_query' => $search_query,
    ];
    $search_data_entity = $this->searchDataStorage->create($values);
    $search_data_entity->save();
  }
}
