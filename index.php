<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once "vendor/autoload.php";
use \FeaturePhp as fphp;

class Renderer {
    private $page;

    private function getPages() {
        return json_decode(file_get_contents(__DIR__."/pages.json"), true);
    }

    private function getPage($slug) {
        foreach ($this->getPages() as $page)
                if ($page["slug"] === $slug)
                    return $page;
    }

    public function __construct($slug) {
        if (!$slug)
            $slug = "index";
        $this->page = $this->getPage($slug);
        if (!$this->page)
            $this->page = $this->getPage("index");
        header("Content-Type: text/html; charset=utf-8");
    }

    private function getProperty($prop, $page = null) {
        if (!$page)
            $page = $this->page;
        return array_key_exists($prop, $page) ? $page[$prop] : "";
    }

    public function render() {
        echo fphp\File\TemplateFile::render(
            "layout.html",
            array(
                array("assign" => "slug", "to" => $this->getProperty("slug")),
                array("assign" => "style", "to" => $this->getProperty("style") ?: $this->getProperty("slug")),
                array("assign" => "title", "to" => $this->getProperty("title")),
                array("assign" => "body", "to" => $this->getProperty("body")),
                array("assign" => "background", "to" => $this->getProperty("background")),
                array("assign" => "navigation", "to" => $this->getNavigation()),
                array("assign" => "overviewNavigation", "to" => $this->getOverviewNavigation()),
                array("assign" => "license", "to" => $this->getLicense()),
                array("assign" => "songs", "to" => $this->getSongs())
            ),
            __DIR__);
    }

    private function getNavigation() {
        $nav = "";
        foreach ($this->getPages() as $page) {
            $href = isset($page["slug"]) ? "?p=$page[slug]" :
                  (isset($page["href"]) ? $page["href"] : "javascript:void(0)");
            $active = isset($page["slug"]) && $this->getProperty("slug") === $page["slug"] ? "active" : "";
            $nav .= "<li class=\"$active\"><a href=\"$href\">$page[title]</a></li>\n";
        }
        return $nav;
    }

    private function getOverviewNavigation() {
        $nav = "<ul class=\"sheet-music overview\">";
        foreach ($this->getPages() as $page) {
            if (!$this->getProperty("summary", $page))
                continue;
            $href = isset($page["slug"]) ? "?p=$page[slug]" :
                  (isset($page["href"]) ? $page["href"] : "javascript:void(0)");
            $nav .= "<li><a href=\"$href\"><p><strong>$page[title]</strong></p><p><span>".$this->getProperty("summary", $page)."</span></p></a></li>\n";
        }
        return $nav."</ul>";
    }

    private function getFileLink($type, $song) {
        $url = $this->getProperty($type, $song);
        $labels = array("video" => "Listen", "mid" => "MIDI", "mscz" => "MuseScore", "sib" => "Sibelius");
        $label = isset($labels[$type]) ? $labels[$type] : strtoupper($type);
        return $url ? "<a href=\"$url\" target=\"_blank\">$label</a>" : "";
    }

    private function getLicense() {
        return "<p>This sheet music is licensed under <a href=\"license.html\">CC BY 4.0</a>.</p>";
    }

    private function getSongs() {
        $songs = $this->getProperty("songs");
        if (!$songs)
            return "";
        $html = "<ul class=\"sheet-music\">";
        foreach ($songs as $song) {
            $html .= fphp\File\TemplateFile::render(
                "song.html",
                array(
                    array("assign" => "number", "to" => $this->getProperty("number", $song)),
                    array("assign" => "title", "to" => $this->getProperty("title", $song)),
                    array("assign" => "subtitle", "to" => $this->getProperty("subtitle", $song)),
                    array("assign" => "video", "to" => $this->getFileLink("video", $song)),
                    array("assign" => "pdf", "to" => $this->getFileLink("pdf", $song)),
                    array("assign" => "mid", "to" => $this->getFileLink("mid", $song)),
                    array("assign" => "mp3", "to" => $this->getFileLink("mp3", $song)),
                    array("assign" => "wav", "to" => $this->getFileLink("wav", $song)),
                    array("assign" => "mscz", "to" => $this->getFileLink("mscz", $song)),
                    array("assign" => "sib", "to" => $this->getFileLink("sib", $song)),
                ),
                __DIR__);
        }
        return $html."</ul>";
    }
}

$page = isset($_GET["p"]) ? $_GET["p"] : null;
$renderer = new Renderer($page);
$renderer->render();
