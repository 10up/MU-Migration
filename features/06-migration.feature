Feature: MU-Migration import all command
    Scenario: MU-Migration is able to export a single site into a zip package with themes,plugins and uploads and import into a Multisite Network
        Given a WP multisite subdirectory install
        Given I create multiple sites with dummy content
        Given a WP install in 'singlesite/'
        
        When I run `wp db prefix`
        And save STDOUT as {DB_PREFIX}
        And I run `wp mu-migration export all single-site.zip --themes --plugins --uploads --path=singlesite`
        Then the single-site.zip file should exist

        When I run `wp mu-migration import all single-site.zip --new_url=http://singlesite2.com`
        And I run `wp site list --fields=blog_id,url`
        Then STDOUT should be a table containing rows:
        | blog_id   | url                    | 
        | 4         | http://singlesite2.com/ |

        When I run `wp mu-migration import all single-site.zip --blog_id=4`
        And I run `wp site list --fields=blog_id,url`
        Then STDOUT should be a table containing rows:
        | blog_id   | url                    | 
        | 4         | http://singlesite.com/ |

        When I run `wp mu-migration import all single-site.zip --blog_id=4  --new_url=http://singlesite2.com`
        And I run `wp site list --fields=blog_id,url`
        Then STDOUT should be a table containing rows:
        | blog_id   | url                    | 
        | 4         | http://singlesite2.com/ |