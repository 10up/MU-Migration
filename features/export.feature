Feature: Test an MU-Migration export.

    Scenario: MU-Migration is able to export the users on a single site
        Given a WP install

        When I run `wp user list --format=count`
        And save STDOUT as {USERS_COUNT}

        When I run `wp mu-migration export users users.csv`
        Then the users.csv file should exist
        Then STDOUT should be:
        """
        Success: {USERS_COUNT} users have been exported
        """

        When I run `cat users.csv`
        Then STDOUT should be CSV containing:
            | ID | user_login | user_email        | role          |
            | 1  | admin      | admin@example.com | administrator |

        When I run `wp eval-file {SRC_DIR}/features/tests/csv_matches_user.php users.csv`
        Then STDOUT should be:
        """
        Success
        """
    
    Scenario: MU-Migration is able to export tables of a single site
        Given a WP install

        
 
