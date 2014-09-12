@api
Feature: simple_form
  In order to complete the form,
  As a DRUPAL user,
  I am required to complete required fields.
  
  Background:
  		Given I am logged in as a user with the "administrator" role
  		And "behat" nodes:
  				| title			| field_my_text_field | field_my_image_field
  		
  Scenario: Navigate to content admin and add behat-demo content
  		 When I visit "node/add/behat-demo"
  		   And I press the "Save" button
  		 Then I should see the text "My Required Text field is required."
  		 