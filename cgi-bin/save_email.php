<?php

//echo ( dirname($_SERVER['SCRIPT_FILENAME']) );

$PARENTDIR = dirname($_SERVER['SCRIPT_FILENAME']);
$SURVEY_RESULTS_DIRECTORY_RELATIVE_TO_PARENT = "/../../survey";
$SURVEY_RESULT_FILENAME = "/pellets.csv";


include_once( $PARENTDIR . '/inc/config.php' );
$template = new fTemplating($PARENTDIR . '/inc/templates/');
//echo("here");
$keep_on_trucking = true;
try {
    
  //make sure the data is valid
  $validator = new fValidation();
  $validator->addRequiredFields( 'i_accept', 'email_address', 'unique_code' );
  $validator->addEmailFields('email_address');
  $validator->validate();
  
} catch (fValidationException $e) {
    //echo("bob");
    //echo($e->getMessage());
    $template->set('error_message', $e->getMessage() );
   // $template->set('rejected_email_results', 'rejected_email_results.php');
    $template->inject('rejected_email_results.php');
    $keep_on_trucking = false;
}
//echo("here 2");  
if ($keep_on_trucking) {
  //it must be valid or we wouldn't still be here...
	<%= fields = []
			field_names = []
			input_options_path = ::Webby.site.content_dir + "/../tmp/input_options.yml"
			input_data = YAML.load_file(input_options_path)
			input_data.each do |key, value|
				fields << "$#{key} = fRequest::get('#{value[:name]}', 'string', '#{value[:php_default]}')"
				field_names << "$#{key}"
			end
			
			fields.join(";\n") + ";\n\n" + "$array_to_save = array(#{field_names.join(",")});"
	 %>


  //echo("here 3");
  $csv = fopen('php://temp/maxmemory:'. (5*1024*1024), 'r+');
  fputcsv( $csv, $array_to_save );
  rewind($csv);
  
  $csv_filepath = realpath($PARENTDIR . $SURVEY_RESULTS_DIRECTORY_RELATIVE_TO_PARENT) . $SURVEY_RESULT_FILENAME;
  //echo($csv_filepath);
  //echo("here 4" );
  try {
      $csv_file = new fFile($csv_filepath);
  } catch (fValidationException $e) {
      //if file doesn't exist, we'll get an exception thrown
      $csv_file = fFile::create($csv_filepath, '');
  }
  //echo("here 5");
  $contents = $csv_file->read();
  $csv_file->write( $contents . stream_get_contents($csv) );
  
  //render template 
  $template->set('saved_email_results', 'saved_email_results.php');
  //$template->place();
  $template->inject('saved_email_results.php');  
}

?>

