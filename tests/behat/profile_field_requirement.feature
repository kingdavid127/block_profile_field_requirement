@block @block_profile_field_requirement @javascript
Feature: Adding profile field requirement block
  In order to restrict access based on profile field
  As a user
  I need to be able to use block profile field requirement

  Scenario: Adding block profile field requirement to the course
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname  | shortname |
      | Course 1  | c1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | c1     | editingteacher |
      | student1 | c1     | student        |
    And I log in as "admin"
    And I navigate to "Users > Accounts > User profile fields" in site administration
    And I set the field "datatype" to "Text input"
    And I set the following fields to these values:
      | Short name (must be unique)   | my_required_field   |
      | Name                          | my_required_field   |
      | Who is this field visible to? | Visible to everyone |
    And I click on "Save changes" "button"
    And I log out
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add the "Profile field requirement" block
    And I configure the "block_profile_field_requirement" block
    And I set the field "User profile fields" to "my_required_field"
    And I set the field "Description text" to "My custom description"
    And I press "Save changes"

    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "Update required fields"
    And I should see "My custom description"
    And I set the field "my_required_field" to "filled"
    And I press "Update profile"
    And I should see "Course 1"

    And I log out
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I configure the "block_profile_field_requirement" block
    And I click on "Require verification" "checkbox"
    And I press "Save changes"

    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I should see "Update required fields"
    And I should see "My custom description"
    And I click on "I confirm the information above is accurate." "checkbox"
    And I press "Update profile"
    And I should see "Course 1"
