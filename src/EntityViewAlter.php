<?php

namespace Drupal\external_link_env_sync;

use Drupal\Component\Utility\Html;
use Drupal\Core\Security\TrustedCallbackInterface;

class EntityViewAlter implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['postRender'];
  }

  public static function postRender($html) {
    $config = \Drupal::config('external_link_env_sync.settings');
    $replace_pattern = self::getEnvPattern(trim($config->get('condition_pattern') ?? ''));
    if (!empty($replace_pattern)) {
      // Build array map of search/replace base urls.
      $search_replace = self::getSearchReplaceMap(trim($config->get('search_replace') ?? ''));
      if (!empty($search_replace)) {
        $changed = FALSE;
        try {
          $dom = Html::load($html);
          $xpath = new \DOMXPath($dom);
          foreach ($xpath->query('//a[@href]') as $element) {
            $url = $element->getAttribute('href');
            $url_parts = parse_url($url);
            // Only external urls have a host.
            if ($search = $url_parts['host'] ?? NULL) {
              if ($replace = $search_replace[$search] ?? NULL) {
                $replace_url = str_replace('{{hostname}}', $replace, $replace_pattern);
                $new_url = self::buildUrl(array_merge($url_parts, parse_url($replace_url)));
                if ($new_url != $url) {
                  $element->setAttribute('href', $new_url);
                  $changed = TRUE;
                }
              }
            }
          }
        } catch (\Exception $e) {
          // Do nothing.
        }

        if ($changed) {
          $html = Html::serialize($dom);
        }
      }
    }

    return $html;
  }

  private static function getEnvPattern($patterns) {
    if (!empty($patterns)) {
      $list = explode("\n", $patterns);
      foreach ($list as $condition_pattern) {
        $condition = trim(explode(',', $condition_pattern)[0] ?? '');
        $pattern = rtrim(trim(explode(',', $condition_pattern)[1] ?? ''), '/');
        if (!empty($condition) && !empty($pattern)) {
          $env_varname = trim(explode('=', $condition)[0] ?? '');
          $env_value = trim(explode('=', $condition)[1] ?? '');
          if (getenv($env_varname) == $env_value) {
            return $pattern;
          }
        }
      }
    }
    return '';
  }

  private static function getSearchReplaceMap($list) {
    $map = [];
    if (!empty($list)) {
      foreach (explode("\n", $list) as $item) {
        $search = trim(explode(',', $item)[0] ?? '');
        $replace = trim(explode(',', $item)[1] ?? '');
        if (!empty($search)) {
          $map[$search] = $replace;
        }
      }
    }
    return $map;
  }

  private static function buildUrl($parts) {
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
