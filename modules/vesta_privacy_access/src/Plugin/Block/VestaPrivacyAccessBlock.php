<?php

namespace Drupal\vesta_privacy_access\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Block(
 *   id = "vesta_privacy_access_block",
 *   admin_label = @Translation("Vesta Privacy Access Block")
 * )
 */
class VestaPrivacyAccessBlock extends BlockBase implements ContainerFactoryPluginInterface {

    protected $configFactory;

    public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
        $this->configFactory = $config_factory;
    }

    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static($configuration, $plugin_id, $plugin_definition, $container->get('config.factory'));
    }

    public function build() {
        $config = $this->configFactory->get('vesta_privacy_access.settings');
        $blocks = [];
        $enabled = (int) $config->get('ya_metrika_enable');
        $id = $config->get('id_ya_metrika');
        $mode = $config->get('mode') ?: 'short';

        if ($enabled) {
            if ($mode === 'full') {
                $blocks = [
                    '#theme' => 'vesta_privacy_access_full',
                    '#attached' => [
                        'library' => ['vesta_privacy_access/vesta_privacy_access'],
                        'drupalSettings' => [
                            'vesta_privacy_access' => [
                                'ya_metrika_enable' => $enabled,
                                'id_ya_metrika' => $id,
                                'mode' => 'full',
                            ],
                        ],
                    ],
                    '#privacy_text' => $config->get('privacy_text'),
                ];
            }
            else {
                $blocks = [
                    '#theme' => 'vesta_privacy_access_short',
                    '#attached' => [
                        'library' => ['vesta_privacy_access/vesta_privacy_access'],
                        'drupalSettings' => [
                            'vesta_privacy_access' => [
                                'ya_metrika_enable' => $enabled,
                                'id_ya_metrika' => $id,
                                'mode' => 'short',
                            ],
                        ],
                    ],
                    '#short_privacy_text' => $config->get('short_privacy_text'),
                ];
            }
        }
        return $blocks;
    }
}
