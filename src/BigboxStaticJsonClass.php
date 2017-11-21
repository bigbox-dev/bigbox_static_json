<?php

namespace Drupal\bigbox_static_json;

use Drupal\file\Entity\File;
use Drupal\image\Entity\ImageStyle;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\field\Entity\FieldConfig;

class BigboxStaticJsonClass {

  static function exclude_fields() {
    return ['id', 'uuid', 'label', 'type', 'changed', 'context', 'revision_id', 'langcode', 'uid', 'status', 'created',
      'revision_uid', 'parent_id', 'parent_type', 'parent_field_name', 'behavior_settings', 'default_langcode',
      'revision_translation_affected'];
  }

  function get_field_value($field) {
    if (count($field->getValue()) > 0){
      $values = [];
      foreach ($field->getValue() as $item) {
        if (count($item) == 1){
          $values[] = $item['value'];
        }elseif (count($item) > 1){
          $values[] = $item;
        }
      };
    }else{
      $values = Null;
    }
    return $values;
  }

  function update_json_file($obj) {
    $dir = 'sites/default/files/json_data/';
    $filename = 'static.json';
    if(!is_dir($dir)) mkdir($dir) ;
    $full_path = $dir.$filename;
    $resourse = fopen($full_path,'r');
    $data = fread($resourse, filesize($full_path));
    fclose($resourse);
    $current_data = json_decode($data);
    if (!$current_data) {
      $current_data = new \stdClass();
    }
    foreach ($obj as $key => $value){
      $current_data->$key = $value;
    }
    $resourse = fopen($full_path,'w+');
    fwrite($resourse, json_encode($current_data));
    fclose($resourse);
  }

  function process_image_field($field_value){
    $itemsValues = [];
    if (count($field_value) > 0) {
      foreach ($field_value as $value) {
        $itemValues = new \stdClass();
        $targetId = $value['target_id'];
        $file = File::load($targetId);
        $uri = $file->getFileUri();
        $styles = \Drupal::entityTypeManager()->getStorage('image_style')->loadMultiple();
        foreach ($styles as $style) {
          $name = $style->getName();
          $strName = $name . '_style';
          $itemValues->$strName = ImageStyle::load($name)->buildUrl($uri);
        }
        $itemValues->alt = $value['alt'];
        $itemsValues[] = $itemValues;
      }
    }
    return $itemsValues;
  }

  function menu_update($entity){
    $json_data = new \stdClass();
    $field_value = $entity->get('field_site_menu')->getValue();
    $storage = \Drupal::service('entity.manager')->getStorage('site_menu');
    $itemsValues = [];
    foreach ($field_value as $value) {
      $menuItem = $storage->load($value['target_id']);
      $menuItemField = $menuItem->get('field_menu_item_link');
      $itemValues = new \stdClass();
      $itemValues->url = $menuItemField[0]->getValue()['uri'];
      $itemValues->name = $menuItemField[0]->getValue()['title'];
      $itemsValues[] = $itemValues;
    }
    $json_data->field_site_menu = $itemsValues;
    $this->update_json_file($json_data);
  }

  function global_settings_update($entity){
    $json_data = new \stdClass();
    $fields = $entity->getFields();
    $data = new \stdClass();

    foreach ($fields as $field) {
      $field_name = $field->getName();
      if (!in_array($field_name, $this->exclude_fields())){
        $field_value = $field->getValue();
        $field_type = FieldConfig::loadByName('config_pages','global_settings', $field_name)->getType();

        if ($field_type == 'entity_reference_revisions'){
          $itemsValues = [];
          foreach ($field_value as $value) {
            $pharagraph = Paragraph::load($value['target_id']);
            $itemValues = new \stdClass();
            $paragraphFields = $pharagraph->getFields();
            foreach ($paragraphFields as $paragraphField) {
              $paragraphFieldName = $paragraphField->getName();
              if (!in_array($paragraphFieldName, $this->exclude_fields())) {
                $paragraphFieldType = $pharagraph-> getType();
                $field_type = FieldConfig::loadByName('paragraph',$paragraphFieldType, $paragraphFieldName)->getType();
                if ($field_type == 'image'){
                  $image_list = $this->process_image_field($paragraphField->getValue());
                  if (count($image_list) > 0) {
                    $itemValues->$paragraphFieldName = $image_list;
                  }
                }
                else{
                  if ($this->get_field_value($paragraphField)) $itemValues->$paragraphFieldName = $this->get_field_value($paragraphField);
                }
              }
            }
            if (!empty(get_object_vars($itemValues))) $itemsValues[] = $itemValues;
          }
          $data->$field_name = $itemsValues;

        }elseif ($field_type == 'image'){
          $image_list = $this->process_image_field($field_value);
          if (count($image_list) > 0) {
            $data->$field_name = $image_list;
          }
        }else {
          if ($this->get_field_value($field)) $data->$field_name = $this->get_field_value($field);
        }
      };
    };
    $json_data-> global_settings = $data;
    $this->update_json_file($json_data);
  }
}

