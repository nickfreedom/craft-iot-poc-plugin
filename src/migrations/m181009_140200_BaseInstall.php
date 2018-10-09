<?php

namespace nickleguillou\craftiotpoc\migrations;

use Craft;
use craft\db\Migration;

/**
 * m181009_140200_BaseInstall migration.
 */
class m181009_140200_BaseInstall extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Place migration code here...
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m181009_140200_BaseInstall cannot be reverted.\n";
        return false;
    }
}