Feature: MU-Migration import all command
    Scenario: MU-Migration is able to export a single site into a zip package with themes,plugins and uploads and import into a Multisite Network
        Given a WP multisite subdirectory install
        Given I create multiple sites with dummy content
        Given a WP install in 'singlesite/'
        
        When I run `wp theme install pixgraphy --activate --path=singlesite`
        And I run `wp plugin install jetpack --activate --path=singlesite`
        And I run `wp plugin install shortcode-ui --path=singlesite`
        And I run `wp media import {SRC_DIR}/features/data/images/*.jpg --path=singlesite`
        And I run `wp mu-migration export all single-site.zip --themes --plugins --uploads --path=singlesite`
        Then the single-site.zip file should exist

        When I run `wp mu-migration import all single-site.zip --new_url=http://singlesite2.com`
        And I run `wp site list --fields=blog_id,url`
        Then STDOUT should be a table containing rows:
        | blog_id   | url                     | 
        | 4         | http://singlesite2.com/ |

        When I run `wp mu-migration import all single-site.zip --blog_id=4`
        And I run `wp site list --fields=blog_id,url`
        Then STDOUT should be a table containing rows:
        | blog_id   | url                    | 
        | 4         | http://singlesite.com/ |

        When I run `wp mu-migration import all single-site.zip --blog_id=4  --new_url=http://singlesite2.com --verbose`
        Then STDOUT should contain:
        """
        Uploads paths have been successfully updated: wp-content/uploads -> wp-content/uploads/sites/4 |AND|
        Success: All done, your new site is available at http://singlesite2.com. Remember to flush the cache (memcache, redis etc).
        """

        When I run `wp site list --fields=blog_id,url`
        Then STDOUT should be a table containing rows:
        | blog_id   | url                     | 
        | 4         | http://singlesite2.com/ |

        Then the wp-content/themes/pixgraphy directory should exist
        Then the wp-content/plugins/jetpack directory should exist
        Then the wp-content/plugins/shortcode-ui directory should not exist

        When I run `wp theme list --status=active --field=name --url=singlesite2.com`
        Then STDOUT should be:
        """
        pixgraphy
        """
        When I run `wp plugin list --status-active --field=name --url=singlesite2.com`
        Then STDOUT should contain:
        """
        jetpack
        """
    Scenario: MU-Migration is able to export a subsite into a zip package with themes,plugins and uploads and import into a single site
        Given a WP multisite subdirectory install
        Given I create multiple sites with dummy content
        Given a WP install in 'singlesite/'
        
        When I run `wp theme install pixgraphy --activate --url=example.com/site-2`
        And I run `wp plugin install jetpack --activate --url=example.com/site-2`
        And I run `wp plugin install shortcode-ui --activate-network`
        And I run `wp media import {SRC_DIR}/features/data/images/*.jpg --url=example.com/site-2`
        And I run `wp mu-migration export all sub-site.zip --themes --plugins --uploads --blog_id=2`
        Then the sub-site.zip file should exist

        When I run `wp mu-migration import all sub-site.zip --new_url=http://subsite.com --verbose --path=singlesite`
        Then STDOUT should contain:
        """
        Uploads paths have been successfully updated: wp-content/uploads/sites/2 -> wp-content/uploads |AND|
        Success: All done, your new site is available at http://subsite.com. Remember to flush the cache (memcache, redis etc).
        """
        Then the wp-content/themes/pixgraphy directory should exist
        Then the wp-content/plugins/jetpack directory should exist
        
        When I run `wp option get siteurl --path=singlesite`
        Then STDOUT should be:
        """
        http://subsite.com
        """

        When I run `wp theme list --status=active --field=name --path=singlesite`
        Then STDOUT should be:
        """
        pixgraphy
        """
        When I run `wp plugin list --status-active --field=name --path=singlesite`
        Then STDOUT should contain:
        """
        jetpack |AND|
        shortcode-ui
        """
    Scenario: MU-Migration is able to export a subsite into a zip package with themes,plugins and uploads and import into another subsite
        Given a WP multisite subdirectory install
        Given I create multiple sites with dummy content
        
        When I run `wp theme install pixgraphy --activate --url=example.com/site-2`
        And I run `wp plugin install jetpack --activate --url=example.com/site-2`
        And I run `wp plugin install shortcode-ui --activate-network`
        And I run `wp media import {SRC_DIR}/features/data/images/*.jpg --url=example.com/site-2`
        And I run `wp mu-migration export all sub-site.zip --themes --plugins --uploads --blog_id=2`
        Then the sub-site.zip file should exist

        When I run `wp mu-migration import all sub-site.zip --blog_id=1 --new_url=http://example.com --verbose`
        Then STDOUT should contain:
        """
        Uploads paths have been successfully updated: wp-content/uploads/sites/2 -> wp-content/uploads |AND|
        Success: All done, your new site is available at http://example.com. Remember to flush the cache (memcache, redis etc).
        """
        Then the wp-content/themes/pixgraphy directory should exist
        Then the wp-content/plugins/jetpack directory should exist
        
        When I run `wp option get siteurl`
        Then STDOUT should be:
        """
        http://example.com
        """

        When I run `wp theme list --status=active --field=name`
        Then STDOUT should be:
        """
        pixgraphy
        """
        When I run `wp plugin list --status-active --field=name`
        Then STDOUT should contain:
        """
        jetpack |AND|
        shortcode-ui
        """