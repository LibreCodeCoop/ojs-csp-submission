<?php
class AbstractPlugin {
    /**
     * @var CspSubmissionPlugin
     */
    protected $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }
}