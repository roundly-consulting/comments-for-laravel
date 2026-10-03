<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/comments-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=comments-for-laravel">
    <img src="art/hero.png" alt="Comments for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/comments-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/comments-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/comments-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/comments-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/comments-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/comments-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=comments-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Comments for Laravel

Polymorphic comments for any Laravel model: any model can write them and any model can receive
them. Threaded replies, moderation, likes, reports, media attachments, @mentions and thread
locking come built in.

## Installation

Requires PHP 8.4 and Laravel 12 or 13.

```bash
composer require roundly-consulting/comments-for-laravel
php artisan vendor:publish --tag="comments-migrations"
php artisan vendor:publish --tag="likes-migrations" --tag="reports-migrations" --tag="approvals-migrations" --tag="media-migrations"
php artisan migrate
```

The second publish adds the tables of the likes, reports, approvals and media-library packages
comments builds on. If your models have UUID/ULID keys, set `COMMENTS_KEY_TYPE` **before**
migrating.

## Usage

Give comments with one trait, receive them with another:

```php
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Comments\Traits\GivesComments;
use RoundlyConsulting\Comments\Traits\HasComments;

class Post extends Model
{
    use HasComments;
}

class User extends Model
{
    use GivesComments;
}
```

Then write, reply, read and moderate through the facade:

```php
use RoundlyConsulting\Comments\Facades\Comments;

$comment = Comments::on($post)->as($user)->body('Nice write-up!')->post();

Comments::on($post)->as($author)->reply($comment)->body('Thanks!')->post();  // a threaded reply

$post->threadedComments()->get();               // the public thread: approved comments + replies
Comments::for($post)->pending()->approveAll();  // moderate everything held for review
Comments::lock($post);                          // no new comments on this post
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/comments-for-laravel](https://roundly-consulting.com/open-source/docs/comments-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=comments-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=comments-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=comments-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
