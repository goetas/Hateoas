<?php

namespace Hateoas\Tests\Fixtures;

use Hateoas\Configuration\Relation;
use JMS\Serializer\Annotation as Serializer;
use Hateoas\Configuration\Annotation as Hateoas;

/**
 * @Hateoas\Relation(
 *      "self",
 *      href = "http://adrienbrault.fr",
 *      attributes={"foo":"bar"},
 *      exclusion = @Hateoas\Exclusion(
 *          groups = {"Default", "simple"},
 *          excludeIf = "(object.firstName !== 'Adrien' || object.lastName !== 'Brault')"
 *      )
 * )
 * @Hateoas\Relation(
 *      "computer",
 *      href = "http://www.apple.com/macbook-pro/",
 *      exclusion = @Hateoas\Exclusion(groups = {"Default", "simple"}),
 *      embedded = @Hateoas\Embedded(
 *          "object.getMacbookPro()",
 *          exclusion = @Hateoas\Exclusion(groups = {"Default"})
 *      )
 * )
 * @Hateoas\Relation(
 *      "broken-computer",
 *      embedded = "object.getWindowsComputer()",
 * )
 * @Hateoas\Relation(
 *      "smartphone",
 *      embedded = "object.getiOSSmartphone()"
 * )
 */
class AdrienBrault
{
    /**
     * @Serializer\Groups({"Default", "simple"})
     */
    public $firstName = 'Adrien';

    /**
     * @Serializer\Groups({"Default", "simple"})
     */
    public $lastName = 'Brault';

    public function getMacbookPro()
    {
        return new Computer('MacBook Pro');
    }

    public function getWindowsComputer()
    {
        return new Computer('Windows Computer');
    }

    public function getiOSSmartphone()
    {
        return new Smartphone('iPhone 6');
    }

    public function getAndroidSmartphone()
    {
        return new Smartphone('Nexus 5');
    }

    public function getRelations()
    {
        return array(
            new Relation('dynamic-relation', 'awesome!!!', array('wowowow')),
        );
    }
}
