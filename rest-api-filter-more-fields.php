<?php
/**
 * WP REST API - Filter (More) Fields
 *
 * @package             REST_API_Filter_More_Fields
 * @author              Som Meaden <som@theprojectsomething.com>
 * @license             MIT
 *
 * @wordpress-plugin
 * Plugin Name:         WP REST API - Filter (More) Fields
 * Plugin URI:          https://github.com/theprojectsomething/REST-API-Filter-More-Fields
 * Description:         Filter (more) fields returned by the WP Rest API using syntax similar to Facebook's Graph API. Works alongside other API-extending plugins, allowing deep-selection of data: e.g. "{api-endpoint}?fields=id,title,acf.limit(10){id,title,acf{id}}"
 * Version:             0.1
 * Author:              Som Meaden
 * Author URI:          http://theprojectsomething.com
 * License:             MIT
 * License URI:         https://opensource.org/licenses/MIT
 * Notes:               Adapted from the "WP REST API - filter fields" plugin by Stephan van Rooij [https://git.io/vKzdB]. Works well with "ACF to WP API" plugin 
 */

add_action('rest_api_init','rest_api_fmf_init', 20);

/**
 * Register the fields functionality across posts, comments, taxonomy and terms
 */
function rest_api_fmf_init () {

  // get all public post types
  // default includes 'post','page','attachment' and custom types added before 'init', 20
  $post_types = get_post_types([ 'public' => true ], 'objects');
  $extra_types = ['comment', 'taxonomy', 'term'];

  // add filters for any post types available to the api
  foreach (array_merge($post_types, $extra_types) as $type) {

    if( is_string($type) || @$type->show_in_rest ) {
      add_filter("rest_prepare_" . (is_string($type) ? $type : $type->name), 'rest_api_fmf', 20, 3);
    }

  }
}

/**
 * Process and run the filter
 * e.g. "id,title,acf{date,tags.limit(5)},posts.limit(2)"
 */
function rest_api_fmf ($data, $post, $request) {

  // get the fields argument from the request
  $fields = $request->get_param('fields');

  // if undefined return unmodified
  if(!$fields) return $data;

  // get the filter
  $filter = rest_api_fmf_filter($fields);

  // if the filter is empty return unmodified
  if( !count($filter->fields) ) return $data;  

  // filter fields recursively and return
  return rest_api_fmf_loop($data->data, $filter->fields);

}


/**
 * Recursively loop over fields
 */
function rest_api_fmf_loop (&$data, &$fields) {

  // create new array
  $data_filtered = [];

  // convert to array
  if( is_object($data) ) $data = (array)$data;

  // The original data is in $data object in the property data
  // For each property inside the data, check if the key is in the filter.
  foreach ($fields as $field) {

    // skip if field doesn't exist
    if( !isset($data[ $field->name ]) ) continue;

    // get field value
    $data_field = $data[ $field->name ];

    // normalise objects
    if( is_object($data_field) ) $data_field = (array)$data_field;

    // check for child fields and run loop recursively
    if( isset($field->fields) && is_array($data_field) ) {
      
      // loop over numeric arrays to account for lists of post-like objects
      if( rest_api_fmf_is_numeric($data_field) ) {
        foreach ($data_field as &$sub) {

          // only recurse over post-like items
          if( is_array($sub) || is_object($sub) ) {
            $sub = rest_api_fmf_loop($sub, $field->fields);
          }
        }

      } else {

        // recurse through post-like item
        $data_field = rest_api_fmf_loop($data_field, $field->fields);
      }
    }

    // loop over any modifiers
    // e.g. "limit(1)"
    if( isset($field->modifiers) ) {
      foreach ($field->modifiers as $modifier) {

        // if valid limit modifer and value is numeric array, shorten the array
        if( $modifier->name==="limit" &&
            isset($modifier->value) &&
            rest_api_fmf_is_numeric($data_field) ) {
          $data_field = array_slice($data_field, 0, (int)$modifier->value);
        }
      }
    } 

    // add the element to the filtered results
    $data_filtered[ $field->name ] = $data_field;
  }

  return count( $data_filtered ) ? $data_filtered : $data;
}


/**
 * Check for a numeric array (helper) 
 */
function rest_api_fmf_is_numeric ($array) {
  return is_array($array) && array_keys($array) === range(0, count($array) - 1);
}


/**
 * Process the fields request
 * e.g. "id,title,custom_field{date,tags.limit(5)},posts.limit(2)"
 */
function rest_api_fmf_filter ($fields_request) {

  // create new fields and levels object
  $filter = (object)[ "fields" => [] ];
  $levels = [ &$filter ];

  // match nested fields e.g. "custom_field{date,tags.limit(5)}"
  // ** this could have used a big dirty recursive regex but it seemed all too complicated
  preg_replace_callback("/([^{},]+)([{}]+)?,?/", function ($result) use (&$levels){

    // split field name and modifiers e.g. "name.modifier.modifier(arg)"
    $args = explode(".", $result[1]);

    // create temporary field object and define name
    $field = (object)[];
    $field->name = array_shift($args);

    // list any modifiers that exist (extra args) 
    if(count($args)) {
      $field->modifiers = [];
      foreach ($args as $arg) {

        // eg: "modifier", "modifer(arg)"
        preg_match("/([^(]+)(?:\(([^)]+)\))?/", $arg, $arg_match);
        $field->modifiers[] = (object)[
          "name" => $arg_match[ 1 ],
          "value" => isset($arg_match[ 2 ]) ? $arg_match[ 2 ] : true
        ];
      }
    }
    
    // get current field level and add new field
    $current = array_slice($levels, -1)[0];
    if(!isset($current->fields)) $current->fields = [];
    $current->fields[] = $field;

    // check suffix and move up or down field levels (if required) for next match
    $suffix = $result[2] ? substr($result[2], 0, 1) : 0;
    if($suffix === "}") array_splice($levels, -strlen($result[2]));
    else if($suffix === "{") $levels[] = $field;

  }, str_replace(" ", "", $fields_request)); // remove spaces from the request

  return $filter;
}