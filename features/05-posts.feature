Feature: Test MU-Migration posts command.

    Scenario: MU-Migration is able to import the users and tables from a single site into a subsite and update the authors
        Given a WP multisite subdirectory install
        Given I create multiple sites with dummy content
        Given a WP install in 'singlesite/'

        When I run `wp user create ann ann@example.com  --path=singlesite`
        And I run `wp post generate --count=10 --post_type=post --post_author=ann --path=singlesite`
        And I insert arbitrary UID postmeta data for user "ann@example.com" in site "singlesite"
        And I run `wp mu-migration export users users.csv --path=singlesite`
        And I run `wp mu-migration export tables tables.sql --path=singlesite`
        And I run `wp mu-migration import users users.csv --blog_id=2 --map_file=users-mapping.json`
        Then the users-mapping.json file should exist

        When I run `wp db prefix --path=singlesite`
        And save STDOUT as {DB_PREFIX}
        And I run `wp db prefix --url=example.com/site-2`
        And save STDOUT as {SUB_DB_PREFIX}
        And I run `wp mu-migration import tables tables.sql --blog_id=2 --old_prefix={DB_PREFIX} --new_prefix={SUB_DB_PREFIX} --old_url=http://singlesite.com --new_url=http://example.com/site-2`
        And I run `wp mu-migration posts update_author users-mapping.json --blog_id=2 --uid_fields='_a_userid_field'`
        Then STDOUT should not contain:
        """
        records failed to update its post_author:
        """

        When I run `wp user get $(wp post get 5 --url=example.com/site-2 --field=post_author) --field=login`
        Then STDOUT should be:
        """
        ann
        """

        When I run `wp user get $(wp post meta get 5 --url=example.com/site-2 _a_userid_field) --field=login`
        Then STDOUT should be:
        """
        ann
        """
