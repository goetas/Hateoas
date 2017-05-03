<?php

namespace Hateoas\Tests\Serializer;

use Hateoas\HateoasBuilder;
use Hateoas\Tests\Fixtures\AdrienBrault;
use Hateoas\Tests\Fixtures\Foo1;
use Hateoas\Tests\Fixtures\Foo2;
use Hateoas\Tests\Fixtures\Foo3;
use Hateoas\Tests\Fixtures\Gh236Foo;
use Hateoas\Tests\TestCase;
use JMS\Serializer\SerializationContext;

class JsonHalSerializerTest extends TestCase
{
    public function testSerializeAdrienBrault()
    {
        $hateoas      = HateoasBuilder::buildHateoas();
        $adrienBrault = new AdrienBrault();

        $this->assertSame(
            <<<JSON
{
    "first_name": "Adrien",
    "last_name": "Brault",
    "_links": {
        "self": {
            "href": "http:\/\/adrienbrault.fr",
            "foo": "bar"
        },
        "computer": {
            "href": "http:\/\/www.apple.com\/macbook-pro\/"
        }
    },
    "_embedded": {
        "computer": {
            "name": "MacBook Pro"
        },
        "broken-computer": {
            "name": "Windows Computer"
        },
        "smartphone": {
            "name": "iPhone 6"
        }
    }
}
JSON
            ,
            $this->json($hateoas->serialize($adrienBrault, 'json'))
        );
    }

    public function testSerializeInlineJson()
    {
        $this->markTestSkipped("inline not supported yet");

        $foo1 = new Foo1();
        $foo2 = new Foo2();
        $foo3 = new Foo3();
        $foo1->inline = $foo2;
        $foo2->inline = $foo3;

        $hateoas = HateoasBuilder::buildHateoas();

        $this->assertSame(
            <<<JSON
{
    "_links": {
        "self3": {
            "href": "foo3"
        },
        "self2": {
            "href": "foo2"
        },
        "self1": {
            "href": "foo1"
        }
    },
    "_embedded": {
        "self3": "foo3",
        "self2": "foo2",
        "self1": "foo1"
    }
}
JSON
            ,
            $this->json($hateoas->serialize($foo1, 'json'))
        );
    }

    public function testGh236()
    {
        $data = [new Gh236Foo()];

        $hateoas = HateoasBuilder::buildHateoas();

        $this->assertSame(
            <<<JSON
[
    {
        "a": {
            "xxx": "yyy"
        },
        "_embedded": {
            "b_embed": {
                "xxx": "zzz"
            }
        }
    }
]
JSON
            ,
            $this->json(
                $hateoas->serialize($data, 'json', SerializationContext::create()->enableMaxDepthChecks())
            )
        );
    }
}
