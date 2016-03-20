# MU-Migration

This WP-CLI plugin makes the process of moving sites from single WordPress sites to a Multisite instance much easier. 
It exports everthing into a zip package which can be used to automatically import it within the target Multisite installation.

## Install

Clone this repo onto `plugins/` folder and activate it. You need to install this plugin on both the site you're moving and the 
target multisite installation.

## Why I need this?
Moving single WordPress sites to a Multisite enviroment can be challenging, specially if you're moving more than one site to
Multisite. You'd need to replace tables prefix, update post_author and wc_customer_user (if WooCommerce is installed) with the new
users ID (Multisite has a shared users table, so if you're moving more than one site you can't guarantee that users will have the same IDs) and more.

There are also a few housekeeping tasks that needs to be done to make sure that the new site will work smoothly and without loosing any data.

## How it works

With a simple command you can export a whole site into a zip package.

```
$ wp mu-migration export all site.zip --plugins --themes --uploads
```

The above command will export users, tables, plugins folder, themes folder and the uploads folder to a unique zip file that you can
move to the multisite server in order to be imported with the `import all` command. The flags `--plugins --themes --uploads`,
adds the plugins folder, themes folder and uploads folder to the zip file respectively.

The following command can be used to import a site from a zip package.
```
$ wṕ mu-migration import all site.zip
```
It will create a new site within multisite based on the site you have just exported, the import all command will take care
of everything that needs to be done when moving a site to multisite (replacing tables prefix, updating post_author IDs and etc).

If you need to set up a new url for the site you're importing (if importing into staging or local enviroments),
you can pass it to the `import all` command.

```
$ wp mu-migration import all site.zip --new_url=multisite.dev/site
```

After the migration you can also manage users password (reset passwords and/or force users to reset their passwords).
```
$ wp mu-migration update_passwords [<newpassword>] [--blog_id=<blog_id>] [--reset] [--send_email] [--include=<users_id>]  [--exclude=<users_id>]
```

E.g

The following command will update all users passwords of the site with ID 3 to `new_weak_password`.
```
$ wp mu-migration update_passwords new_weak_password --blog_id=3
```

This next command will reset all users passwords to a random secure password and it will send a reset email to all users.

```
$ wp mu-migration update_passwords --reset --blog_id=3 --send_email
```

## Notes
If your theme and plugins have been done in the WordPress way, you shoundn't have major problem after the migration, keep in mind
that some themes may experience incompatibilities issue due to doing things in the wrong way. (E.g hardcoded links like '/contact' etc)
Depending of the site you're migrating you may need to push some fixes to your code.
