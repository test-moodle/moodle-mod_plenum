@mod @mod_plenum @mod_plenum_grade
Feature: Queue
  In order to preside at a meeting
  As a teacher
  I need to allow students to make motions

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student@example.com |
    And the following "courses" exist:
      | shortname | fullname   |
      | C1        | Course 1 |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity   | name       | intro      | course | grade |
      | plenum     | Meeting1   | PageDesc1  | C1     | 100   |
    And the following config values are set as admin:
      | 50   | delay | plenumform_basic |

  @javascript
  Scenario: Grade a students resolution
    Given I am on the "Meeting1" "plenum activity" page logged in as teacher1
    When I click on "Open" "button" in the "region-main" "region"
    And I click on "Save changes" "button" in the "Opening a new session" "dialogue"
    And I wait until the page is ready
    And I log out
    And I am on the "Meeting1" "plenum activity" page logged in as student1

    And I click on "Resolve" "button" in the "region-main" "region"
    And I set the following fields in the "Editing a resolution" "dialogue" to these values:
        | Name       | Thanks               |
        | Resolution | That all sing a song |
    And I click on "Save changes" "button" in the "Editing a resolution" "dialogue"
    And I wait until the page is ready
    And I am on the "Meeting1" "plenum activity" page logged in as teacher1
    And I change window size to "large"
    And I click on "Allow motion" "button" in the "region-main" "region"
    And I click on "Save changes" "button" in the "Confirm" "dialogue"
    And I click on "Grade users" "button" in the "region-main" "region"
    Then I should see "Resolution: Thanks" in the "Grade users" "dialogue"
    And I set the following fields in the "Grade users" "dialogue" to these values:
        | Grade       | 50               |
    And I click on "Save changes" "button" in the "Grade users" "dialogue"
    And I wait until the page is ready
    And I log out
    And I am on the "Meeting1" "plenum activity" page logged in as student1
    And I click on "View grades" "button" in the "region-main" "region"
