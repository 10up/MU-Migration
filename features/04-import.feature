Feature: Test MU-Migration import commands.

    Scenario: MU-Migration is able to import the users from a single site into a subsite
        Given a WP multisite subdirectory install
        Given I create multiple sites with dummy content
        Given a WP install in 'singlesite/'

        When I run `wp user generate --count=100 --path=singlesite`
        And I run `wp user list --format=count --path=singlesite`
        And save STDOUT as {SINGLE_SITE_USERS_COUNT}
        And I run `wp mu-migration export users users.csv --path=singlesite`
        Then the users.csv file should exist
        Then STDOUT should be:
        """
        Success: {SINGLE_SITE_USERS_COUNT} users have been exported
        """
        When I run `wp user list --format=count --url=example.com/site-2`
        Then STDOUT should be:
        """
        11
        """
        When I run `wp mu-migration import users users.csv --blog_id=2 --map_file=users-mapping.json`
        Then STDOUT should contain:
        """
        Parsing users.csv...
        Success: A map file has been created: users-mapping.json
        Success: 90 users have been imported and 11 users already existed
        """
        Then the users-mapping.json file should exist
        When I run `wp eval-file {SRC_DIR}/features/tests/csv_matches_user.php users.csv users-mapping.json --url=example.com/site-2`
        Then STDOUT should be:
        """
        Success
        """