Feature: Test an MU-Migration info command.

  Scenario: MU-Migration info works
    Given a WP install
    When I run `wp mu-migration info`
    Then STDOUT should contain:
      """
MU-Migration version: %Yv{MU_MIGRATION_VERSION}%n

Created by Nícholas André at 10up
Github: https://github.com/10up/MU-Migration
      """
