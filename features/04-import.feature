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
    Scenario: MU-Migration is able to import the users from a subsite into a single site
        Given a WP multisite subdirectory install
        Given I create multiple sites with dummy content
        Given a WP install in 'singlesite/'

        When I run `wp user list --format=count --blog_id=2`
        And save STDOUT as {SUBSITE_USERS_COUNT}
        And I run `wp mu-migration export users users.csv  --blog_id=2`
        Then the users.csv file should exist
        Then STDOUT should be:
        """
        Success: {SUBSITE_USERS_COUNT} users have been exported
        """
        When I run `wp user list --format=count --path=singlesite`
        Then STDOUT should be:
        """
        1
        """
        When I run `wp mu-migration import users users.csv --path=singlesite --map_file=users-mapping.json`
        Then STDOUT should contain:
        """
        Parsing users.csv...
        Success: A map file has been created: users-mapping.json
        Success: 10 users have been imported and 1 users already existed
        """
        Then the users-mapping.json file should exist
        When I run `wp eval-file {SRC_DIR}/features/tests/csv_matches_user.php users.csv users-mapping.json --path=singlesite`
        Then STDOUT should be:
        """
        Success
        """
    Scenario: MU-Migration is able to import the users from a subsite into another subsite
        Given a WP multisite subdirectory install
        Given I create multiple sites with dummy content

        When I run `wp user list --format=count --blog_id=2`
        And save STDOUT as {SUBSITE_USERS_COUNT}
        And I run `wp mu-migration export users users.csv  --blog_id=2`
        Then the users.csv file should exist
        Then STDOUT should be:
        """
        Success: {SUBSITE_USERS_COUNT} users have been exported
        """
        When I run `wp user list --format=count --url=example.com/site-3`
        Then STDOUT should be:
        """
        11
        """
        When I run `wp mu-migration import users users.csv --blog_id=3 --map_file=users-mapping.json`
        Then STDOUT should contain:
        """
        Parsing users.csv...
        Success: A map file has been created: users-mapping.json
        Success: 0 users have been imported and 11 users already existed
        """
        Then the users-mapping.json file should exist
        When I run `wp eval-file {SRC_DIR}/features/tests/csv_matches_user.php users.csv users-mapping.json --url=example.com/site-3`
        Then STDOUT should be:
        """
        Success
        """
    Scenario: MU-Migration is able to import tables from single site into a subsite
        Given a WP multisite subdirectory install
        Given I create multiple sites with dummy content
        Given a WP install in 'singlesite/'

        When I run `wp post create --post_type=page --post_title='Test Post' --post_status=publish --post_date='2016-12-01 07:00:00' --path=singlesite`
        And I run `wp mu-migration export tables tables.sql --path=singlesite`
        And I run `wp db prefix --path=singlesite`
        And save STDOUT as {DB_PREFIX}
        And I run `wp db prefix --url=example.com/site-2`
        And save STDOUT as {SUB_DB_PREFIX}
        And I run `wp mu-migration import tables tables.sql --blog_id=2 --old_prefix={DB_PREFIX} --new_prefix={SUB_DB_PREFIX} --old_url=http://singlesite.com --new_url=http://example.com/site-2`
        Then STDOUT should be:
        """
        Database imported
        Running search-replace
        Search and Replace has been successfully executed
        Running Search and Replace for uploads paths
        Uploads paths have been successfully updated: wp-content/uploads -> wp-content/uploads/sites/2
        """

        When I run `wp option get siteurl --url=http://example.com/site-2`
        Then STDOUT should be:
        """
        http://example.com/site-2
        """
    Scenario: MU-Migration is able to import tables from a subsite into another subsite
        Given a WP multisite subdirectory install
        Given I create multiple sites with dummy content

        When I run `wp mu-migration export tables tables.sql --blog_id=3`
        And I run `wp db prefix --url=example.com/site-3`
        And save STDOUT as {DB_PREFIX}
        And I run `wp db prefix --url=example.com/site-2`
        And save STDOUT as {SUB_DB_PREFIX}
        And I run `wp mu-migration import tables tables.sql --blog_id=2 --original_blog_id=3 --old_prefix={DB_PREFIX} --new_prefix={SUB_DB_PREFIX} --old_url=http://example.com/site-3 --new_url=http://example.com/site-2`
        Then STDOUT should be:
        """
        Database imported
        Running search-replace
        Search and Replace has been successfully executed
        Running Search and Replace for uploads paths
        Uploads paths have been successfully updated: wp-content/uploads/sites/3 -> wp-content/uploads/sites/2
        """
        When I run `wp option get siteurl --url=http://example.com/site-2`
        Then STDOUT should be:
        """
        http://example.com/site-2
        """
       
        When I run `wp mu-migration export tables tables.sql --blog_id=3`
        And I run `wp db prefix --url=example.com/site-3`
        And save STDOUT as {DB_PREFIX}
        And I run `wp db prefix --url=example.com`
        And save STDOUT as {SUB_DB_PREFIX}
        And I run `wp mu-migration import tables tables.sql --blog_id=1 --original_blog_id=3 --old_prefix={DB_PREFIX} --new_prefix={SUB_DB_PREFIX} --old_url=http://example.com/site-3 --new_url=http://example.com`
        Then STDOUT should be:
        """
        Database imported
        Running search-replace
        Search and Replace has been successfully executed
        Running Search and Replace for uploads paths
        Uploads paths have been successfully updated: wp-content/uploads/sites/3 -> wp-content/uploads
        """
        When I run `wp option get siteurl --url=http://example.com/`
        Then STDOUT should be:
        """
        http://example.com
        """

    Scenario: MU-Migration is able to import tables from a subsite into a single site
        Given a WP multisite subdirectory install
        Given I create multiple sites with dummy content
        Given a WP install in 'singlesite/'
        
        When I run `wp mu-migration export tables tables.sql --blog_id=3`
        And I run `wp db prefix --url=example.com/site-3`
        And save STDOUT as {DB_PREFIX}
        And I run `wp db prefix --path=singlesite`
        And save STDOUT as {SINGLE_DB_PREFIX}
        And I run `wp mu-migration import tables tables.sql --blog_id=1 --original_blog_id=3 --old_prefix={DB_PREFIX} --new_prefix={SINGLE_DB_PREFIX} --old_url=http://example.com/site-3 --new_url=http://singlesite.com --path=singlesite`
        Then STDOUT should be:
        """
        Database imported
        Running search-replace
        Search and Replace has been successfully executed
        Running Search and Replace for uploads paths
        Uploads paths have been successfully updated: wp-content/uploads/sites/3 -> wp-content/uploads
        """
        When I run `wp option get siteurl --path=singlesite`
        Then STDOUT should be:
        """
        http://singlesite.com
        """