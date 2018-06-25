<?php
/**
 * CraftIoTPoc plugin for Craft CMS 3.x
 *
 * This twig plugin for the Craft CMS brings convenient json_decode filter to your
 * Twig templates.
 *
 * @link      https://github.com/nickfreedom
 * @copyright Copyright (c) 2018 Nick Le Guillou

 */

namespace nickleguillou\craftiotpoc\twigextensions;

use Craft;
use craft\helpers\Json;

/**
 * @author    Nick Le Guillou
 * @package   CraftIotPoc
 * @since     1.0.0
 */
class CraftIoTPocTwigExtension extends \Twig_Extension
{
    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'CraftIoTPoc';
    }

    /**
     * @inheritdoc
     */
    public function getFilters()
    {
        return [
            new \Twig_SimpleFilter('json_decode', [$this, 'jsonDecodeFilter']),
        ];
    }


    /**
     * json_decode
     *
     * @param $encoded
     * @return mixed
     */
    public function jsonDecodeFilter($encoded)
    {
        $json = Json::decode($encoded);
        return $json;
    }
}