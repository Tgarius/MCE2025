<?php
/*
 * UnderConstructionPage
 * Ambulance theme
 * (c) WebFactory Ltd, 2015 - 2025
 */


// this is an include only WP file
if (!defined('ABSPATH')) {
  die;
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>[title]</title>
    <meta name="description" content="[description]" />
    <meta name="generator" content="[generator]">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=Roboto:400,900"><?php //phpcs:ignore ?>
    [head]
  </head>

  <body>
    <div class="container">
      <div class="row">
        <div class="col-xs-12 col-md-12 col-lg-12">
          <h1>[heading1]</h1>
        </div>
      </div>
    </div>

    <div id="hero-image">
      <img src="[theme-url]ambulance.png" alt="Our site is getting some much needed care" title="Our site is getting some much needed care">
    </div>
    <div class="container">

      <div class="row">
        <div class="col-xs-12 col-md-8 col-md-offset-2 col-lg-offset-2 col-lg-8">
          <p class="content">[content]</p>
        </div>
      </div>

      <div class="row" id="social">
        <div class="col-xs-12 col-md-12 col-lg-12">
          [social-icons]
        </div>
      </div>

    </div>
    [footer]
  </body>
</html>
