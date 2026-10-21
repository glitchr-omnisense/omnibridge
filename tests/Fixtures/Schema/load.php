<?php

/*
 * The fixtures of the Schema tests, required by hand: they are autoload-dev,
 * which an application running this suite from its vendor/ does not have.
 */

require_once __DIR__.'/State.php';
require_once __DIR__.'/Translation.php';
require_once __DIR__.'/LocaleRule.php';
require_once __DIR__.'/Entity/Timestamps.php';
require_once __DIR__.'/Entity/Thread.php';
require_once __DIR__.'/Entity/Article.php';
require_once __DIR__.'/Entity/Tag.php';
require_once __DIR__.'/Entity/User.php';
require_once __DIR__.'/Entity/Profile.php';
require_once __DIR__.'/Entity/Notice.php';
require_once __DIR__.'/Entity/Alert.php';

return [
    Omnibridge\Tests\Fixtures\Schema\Entity\Timestamps::class,
    Omnibridge\Tests\Fixtures\Schema\Entity\Thread::class,
    Omnibridge\Tests\Fixtures\Schema\Entity\Article::class,
    Omnibridge\Tests\Fixtures\Schema\Entity\Tag::class,
    Omnibridge\Tests\Fixtures\Schema\Entity\User::class,
    Omnibridge\Tests\Fixtures\Schema\Entity\Profile::class,
    Omnibridge\Tests\Fixtures\Schema\Entity\Notice::class,
    Omnibridge\Tests\Fixtures\Schema\Entity\Alert::class,
];
