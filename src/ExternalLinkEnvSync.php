<?php

namespace Drupal\external_link_env_sync;

use Drupal\Component\Utility\Html;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;

class ExternalLinkEnvSync implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['alter'];
  }

  public static function alter(mixed $value) : mixed {
    if (is_object($value) && $value instanceof Url) {
      $value = self::alterUrl($value);
    }
    else {
      $value = self::alterMarkup($value);
    }

    return $value;
  }

  private static function alterUrl(Url $link) : Url {
    if ($link->isExternal()) {
      $url = $link->getUri();
      $new_url = self::replaceUrl($url);
      if ($new_url != $url) {
        $link = Url::fromUri($new_url)->setOptions($link->getOptions());
      }
    }
    return $link;
  }

  private static function alterMarkup(string $html) : string {
    $changed = FALSE;
    try {
      $dom = Html::load($html);
      $xpath = new \DOMXPath($dom);
      foreach ($xpath->query('//a[@href]') as $element) {
        $url = $element->getAttribute('href');
        $new_url = self::replaceUrl($url);
        if ($new_url != $url) {
          $element->setAttribute('href', $new_url);
          $changed = TRUE;
        }
      }
    } catch (\Exception $e) {
      // Do nothing.
    }
    if ($changed) {
      $html = Html::serialize($dom);
    }
    return $html;
  }

  private static function replaceUrl(string $url) : string {
    static $config;
    if (!isset($config)) {
      $config = self::getConfig();
    }
    if (!empty($config['replace_pattern']) && !empty($config['search_replace_map'])) {
      $url_parts = parse_url($url);
      if ($search = $url_parts['host'] ?? NULL) {
        if ($replace = $config['search_replace_map'][$search] ?? NULL) {
          $replace_url = str_replace('{{hostname}}', $replace, $config['replace_pattern']);
          $url = self::buildUrl(array_merge($url_parts, parse_url($replace_url)));
        }
      }
    }
    return $url;
  }

  private static function getConfig() : array {
    $replace_pattern = '';
    $search_replace_map = [];

    if ($config = \Drupal::config('external_link_env_sync.settings')) {
      $condition_patterns = trim($config->get('condition_pattern') ?? '');
      if (!empty($condition_patterns)) {
        foreach (explode("\n", $condition_patterns) as $condition_pattern) {
          $condition = trim(explode(',', $condition_pattern)[0] ?? '');
          $pattern = rtrim(trim(explode(',', $condition_pattern)[1] ?? ''), '/');
          if (!empty($condition) && !empty($pattern)) {
            $env_varname = trim(explode('=', $condition)[0] ?? '');
            $env_value = trim(explode('=', $condition)[1] ?? '');
            if (getenv($env_varname) == $env_value) {
              $replace_pattern = $pattern;
            }
          }
        }
      }
      $search_replaces = trim($config->get('search_replace') ?? '');
      if (!empty($search_replaces)) {
        foreach (explode("\n", $search_replaces) as $search_replace) {
          $search = trim(explode(',', $search_replace)[0] ?? '');
          $replace = trim(explode(',', $search_replace)[1] ?? '');
          if (!empty($search)) {
            $search_replace_map[$search] = $replace;
          }
        }
      }
    }

    return [
      'replace_pattern' => $replace_pattern,
      'search_replace_map' => $search_replace_map,
    ];
  }

  private static function buildUrl(array $parts) : string {
    $url = '';
    if (isset($parts['scheme'])) {
        $url .= $parts['scheme'] . '://';
    }
    if (isset($parts['user'])) {
        $url .= $parts['user'];
        if (isset($parts['pass'])) {
            $url .= ':' . $parts['pass'];
        }
        $url .= '@';
    }
    if (isset($parts['host'])) {
        $url .= $parts['host'];
    }
    if (isset($parts['port'])) {
        $url .= ':' . $parts['port'];
    }
    if (isset($parts['path'])) {
        $url .= $parts['path'];
    }
    if (isset($parts['query'])) {
        $url .= '?' . $parts['query'];
    }
    if (isset($parts['fragment'])) {
        $url .= '#' . $parts['fragment'];
    }
    return $url;
  }

}
