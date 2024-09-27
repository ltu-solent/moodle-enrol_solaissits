@enrol @enrol_solaissits @javascript
Feature: Testing bulk_actions in enrol_solaissits
  In order to be able to manage enrolments
  As an manager
  I can bulk process SITS enrolments

  Background:
    Given the following "users" exist:
    | username | firstname | lastname | email                |
    | student1 | Student   | One      | student1@example.com |
    | student2 | Student   | Two      | student2@example.com |
    | student3 | Student   | Three    | student3@example.com |
    | student4 | Student   | Four     | student4@example.com |
    | student5 | Student   | Five     | student5@example.com |
    | student6 | Student   | Six      | student6@example.com |
    | manual1  | Manual    | One      | manual1@example.com  |
    | teacher1 | Teacher   | One      | teacher1@example.com |
    | teacher2 | Teacher   | Two      | teacher2@example.com |
    And the following "courses" exist:
    | fullname | shortname | category |
    | Course1  | C1        | 0        |
    And the following "enrol_solaissits > enrolment methods" exist:
    | course | method     |
    | C1     | solaissits |
    And the following "course enrolments" exist:
    | user     | course | role           | enrol      |
    | student1 | C1     | student        | solaissits |
    | student2 | C1     | student        | solaissits |
    | student3 | C1     | student        | solaissits |
    | student4 | C1     | student        | solaissits |
    | student5 | C1     | student        | solaissits |
    | student6 | C1     | student        | solaissits |
    | manual1  | C1     | student        | manual     |
    | teacher1 | C1     | editingteacher | solaissits |
    | teacher2 | C1     | teacher        | manual     |

  Scenario: Tutors cannot perform bulk actions on SITS enrolments
    Given I am logged in as "teacher1"
    And I am on "C1" course homepage
    And I follow "Participants"
    When I click on "select-all-participants" "checkbox"
    Then "optgroup[label='SITS enrolments']" "css_element" should not exist

  Scenario: Site managers can perform bulk actions on SITS enrolments
    # Suspending users before bulk deletion helps protect against accidental deletion, as there's no easy way back.
    Given I am logged in as "admin"
    And I am on "C1" course homepage
    And I follow "Participants"
    When I click on "select-all-participants" "checkbox"
    Then "optgroup[label='SITS enrolments']" "css_element" should exist
    And "Edit selected user enrolments" "option" should exist in the "optgroup[label='SITS enrolments']" "css_element"
    And "Delete selected user enrolments" "option" should exist in the "optgroup[label='SITS enrolments']" "css_element"
    When I click on "Edit selected user enrolments" "option" in the "optgroup[label='SITS enrolments']" "css_element"
    Then I should see "User \"Manual One\" was removed from the selection"
    And I should see "User \"Teacher Two\" was removed from the selection"
    When I set the field "Alter status" to "Suspended"
    And I press "Save changes"
    Then the following should exist in the "participants" table:
    | -2-           | -7-       |
    | Student Five  | Suspended |
    | Student Four  | Suspended |
    | Student One   | Suspended |
    | Teacher One   | Suspended |
    | Manual One    | Active    |
    | Student Six   | Suspended |
    | Student Three | Suspended |
    | Student Two   | Suspended |
    | Teacher Two   | Active    |
    When I click on "select-all-participants" "checkbox"
    And I click on "Delete selected user enrolments" "option" in the "optgroup[label='SITS enrolments']" "css_element"
    Then I should see "User \"Manual One\" was removed from the selection"
    And I should see "User \"Teacher Two\" was removed from the selection"
    And I should see "Delete selected user enrolments"
    When I press "Unenrol users"
    Then the following should exist in the "participants" table:
    | -2-           | -7-       |
    | Manual One    | Active    |
    | Teacher Two   | Active    |
    Then the following should not exist in the "participants" table:
    | -2-           |
    | Student Five  |
    | Student Four  |
    | Student One   |
    | Teacher One   |
    | Student Six   |
    | Student Three |
    | Student Two   |

  Scenario: Site managers cannot bulk delete Active SITS enrolments
    # Need to suspend first.
    Given I am logged in as "admin"
    And I am on "C1" course homepage
    And I follow "Participants"
    When I click on "select-all-participants" "checkbox"
    Then "optgroup[label='SITS enrolments']" "css_element" should exist
    And "Delete selected user enrolments" "option" should exist in the "optgroup[label='SITS enrolments']" "css_element"
    When I click on "Delete selected user enrolments" "option" in the "optgroup[label='SITS enrolments']" "css_element"
    Then I should see "User \"Manual One\" was removed from the selection"
    And I should see "User \"Teacher Two\" was removed from the selection"
    And I should see "Delete selected user enrolments"
    When I press "Unenrol users"
    Then the following should exist in the "participants" table:
    | -2-           | -7-    |
    | Student Five  | Active |
    | Student Four  | Active |
    | Student One   | Active |
    | Teacher One   | Active |
    | Manual One    | Active |
    | Student Six   | Active |
    | Student Three | Active |
    | Student Two   | Active |
    | Teacher Two   | Active |
