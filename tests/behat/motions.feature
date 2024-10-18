@mod @mod_plenum @mod_plenum_motion
Feature: Making motions
  In order to preside at a meeting
  As a teacher
  I need to be able to open meeting and make motions

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
      | activity   | name     | intro      | course |
      | plenum     | Meeting1 | PageDesc1  | C1     |

  @javascript
  Scenario: Open meeting and make motion
    Given I am on the "Meeting1" "plenum activity" page logged in as teacher1
    When I click on "Open" "button" in the "region-main" "region"
    And I click on "Save changes" "button" in the "Opening a new session" "dialogue"
    And I wait until the page is ready
    And I log out
    And I am on the "Meeting1" "plenum activity" page logged in as student1
    Then I should see "Resolve" in the "region-main" "region"

  @javascript
  Scenario: Open meeting and view motion
    Given I am on the "Meeting1" "plenum activity" page logged in as teacher1
    When I click on "Open" "button" in the "region-main" "region"
    And I click on "Save changes" "button" in the "Opening a new session" "dialogue"
    And I wait until the page is ready
    And I log out
    And I am on the "Meeting1" "plenum activity" page logged in as student1
    And I click on "Open session" "text"
    Then I should see "Offered by: Teacher 1" in the "View motion" "dialogue"

  @javascript
  Scenario: Make main motion
    Given the following "mod_plenum > motions" exist:
      | plenum   | user      | type  | status |
      | Meeting1 | teacher1  | open  | 1      |
    When I am on the "Meeting1" "plenum activity" page logged in as student1
    Then I should see "Resolve" in the "region-main" "region"

  @javascript
  Scenario: Make second
    Given the following "mod_plenum > motions" exist:
      | plenum   | user      | type     | status | name |
      | Meeting1 | teacher1  | open     | 1      |      |
      | Meeting1 | teacher1  | resolve  | 1      | Move |
    When I am on the "Meeting1" "plenum activity" page logged in as student1
    Then I should not see "Resolve" in the "region-main" "region"
    And I should see "Second" in the "region-main" "region"
