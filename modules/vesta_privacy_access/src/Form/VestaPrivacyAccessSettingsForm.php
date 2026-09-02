<?php

namespace Drupal\vesta_privacy_access\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class VestaPrivacyAccessSettingsForm extends ConfigFormBase {

    public function getFormId() {
        return 'vesta_privacy_access_settings_form';
    }

    protected function getEditableConfigNames() {
        return ['vesta_privacy_access.settings'];
    }

    public function buildForm(array $form, FormStateInterface $form_state) {
        $config = $this->config('vesta_privacy_access.settings');

        $form['ya_metrika_enable'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Включить блок подтверждения использования метрики'),
            '#default_value' => (int) $config->get('ya_metrika_enable'),
        ];

        $form['id_ya_metrika'] = [
            '#type' => 'textfield',
            '#title' => $this->t('ID Яндекс Метрики'),
            '#default_value' => $config->get('id_ya_metrika') ?: '',
            '#states' => [
                'visible' => [
                    ':input[name="ya_metrika_enable"]' => ['checked' => TRUE],
                ],
            ],
        ];

        // Новый переключатель режима.
        $form['mode'] = [
            '#type' => 'select',
            '#title' => $this->t('Режим отображения'),
            '#options' => [
                'short' => $this->t('Сокращенный'),
                'full' => $this->t('Полный'),
            ],
            '#default_value' => $config->get('mode') ?: 'short',
            '#description' => $this->t('По умолчанию используется сокращенный вариант.'),
            '#states' => [
                'visible' => [
                    ':input[name="ya_metrika_enable"]' => ['checked' => TRUE],
                ],
            ],
        ];

        // -------- Сокращенный вариант (минимум настроек) --------
        $short_text_default = '<p><strong>Мы используем Яндекс Метрику</strong>. Аналитика включена. Нажмите «Хорошо», чтобы скрыть уведомление. Подробнее см. <a href="https://yandex.ru/support/metrika/general/opt-out.html" target="_blank" rel="noopener">здесь</a>.</p>';
        $short_privacy_text = $config->get('short_privacy_text') ?: [
            'value' => $short_text_default,
            'format' => 'full_html',
        ];
        $form['short_settings'] = [
            '#type' => 'details',
            '#title' => $this->t('Настройки — Сокращенный вариант'),
            '#open' => TRUE,
            '#states' => [
                'visible' => [
                    ':input[name="ya_metrika_enable"]' => ['checked' => TRUE],
                    [':input[name="mode"]' => ['value' => 'short']],
                ],
            ],
        ];
        $form['short_settings']['short_privacy_text'] = [
            '#type' => 'text_format',
            '#title' => $this->t('Текст уведомления (Short)'),
            '#format' => $short_privacy_text['format'],
            '#default_value' => $short_privacy_text['value'],
            '#allowed_formats' => ['full_html', 'plain_text'],
        ];
        $form['short_settings']['short_title_color'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Цвет заголовка'),
            '#default_value' => $config->get('short_title_color') ?: '#303030',
        ];
        $form['short_settings']['short_text_color'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Цвет текста'),
            '#default_value' => $config->get('short_text_color') ?: '#303030',
        ];
        $form['short_settings']['short_link_color'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Цвет ссылок'),
            '#default_value' => $config->get('short_link_color') ?: '#2da0ce',
        ];
        $form['short_settings']['short_btn_color'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Цвет текста кнопки «Хорошо»'),
            '#default_value' => $config->get('short_btn_color') ?: '#FFFFFF',
        ];
        $form['short_settings']['short_btn_background'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Фон кнопки «Хорошо»'),
            '#default_value' => $config->get('short_btn_background') ?: '#2da0ce',
        ];

        // -------- Полный вариант (как у вас) --------
        $form['full_settings'] = [
            '#type' => 'details',
            '#title' => $this->t('Настройки — Полный вариант'),
            '#open' => FALSE,
            '#states' => [
                'visible' => [
                    ':input[name="ya_metrika_enable"]' => ['checked' => TRUE],
                    [':input[name="mode"]' => ['value' => 'full']],
                ],
            ],
        ];

        $text_default = '<p>Этот сайт использует сервис веб-аналитики Яндекс Метрика ...</p>';
        $privacy_text = $config->get('privacy_text') ?: ['value' => $text_default, 'format' => 'full_html'];
        $form['full_settings']['privacy_text'] = [
            '#type' => 'text_format',
            '#title' => $this->t('Текст согласия (Полный)'),
            '#format' => $privacy_text['format'],
            '#default_value' => $privacy_text['value'],
            '#allowed_formats' => ['full_html', 'plain_text'],
        ];

        // Остальные стили (как у вас было) — поместим внутрь full_settings:
        $full_keys = [
            'font_family' => 'Verdana',
            'title_color' => '#303030',
            'text_color' => '#303030',
            'link_color' => '#303030',
            'btn_color' => '#FFFFFF',
            'btn_color_hover' => '#FFFFFF',
            'btn_background' => '#2da0ce',
            'btn_background_hover' => '#0a75a0',
            'btn_border_width' => '1px',
            'btn_border_color' => '#2da0ce',
            'btn_border_color_hover' => '#0a75a0',
            'btn_border_radius' => '.25rem',
            'transition_animation' => '.3s',
        ];
        foreach ($full_keys as $key => $def) {
            $form['full_settings'][$key] = [
                '#type' => 'textfield',
                '#title' => $this->t('@key', ['@key' => $key]),
                '#default_value' => $config->get($key) ?: $def,
            ];
        }

        $form['actions']['#type'] = 'actions';
        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Сохранить'),
        ];
        $form['#tree'] = TRUE;

        return $form;
    }

    public function submitForm(array &$form, FormStateInterface $form_state) {
        $config = $this->config('vesta_privacy_access.settings');

        // Базовые.
        $config->set('ya_metrika_enable', $form_state->getValue('ya_metrika_enable'));
        $config->set('id_ya_metrika', $form_state->getValue('id_ya_metrika'));
        $config->set('mode', $form_state->getValue('mode') ?: 'short');

        // Short.
        $short = $form_state->getValue(['short_settings', 'short_privacy_text']);
        $config->set('short_privacy_text', $short);
        $config->set('short_title_color', $form_state->getValue(['short_settings','short_title_color']));
        $config->set('short_text_color', $form_state->getValue(['short_settings','short_text_color']));
        $config->set('short_link_color', $form_state->getValue(['short_settings','short_link_color']));
        $config->set('short_btn_color', $form_state->getValue(['short_settings','short_btn_color']));
        $config->set('short_btn_background', $form_state->getValue(['short_settings','short_btn_background']));

        // Full.
        $full_text = $form_state->getValue(['full_settings', 'privacy_text']);
        $config->set('privacy_text', $full_text);
        foreach ([
                     'font_family','title_color','text_color','link_color',
                     'btn_color','btn_color_hover','btn_background','btn_background_hover',
                     'btn_border_width','btn_border_color','btn_border_color_hover',
                     'transition_animation','btn_border_radius',
                 ] as $key) {
            $config->set($key, $form_state->getValue(['full_settings', $key]));
        }

        $config->save();

        // Сбор CSS-переменных под активный режим.
        $module_name = 'vesta_privacy_access';
        $module_path = \Drupal::service('extension.path.resolver')->getPath('module', $module_name);
        $tpl_path = $module_path . '/css/variables.css.tpl';
        $output_path = $module_path . '/css/variables.css';

        if (file_exists($tpl_path)) {
            $mode = $config->get('mode') ?: 'short';

            if ($mode === 'short') {
                $vars = [
                    'font_family' => $config->get('font_family') ?: 'Verdana',
                    'title_color' => $config->get('short_title_color') ?: '#303030',
                    'text_color'  => $config->get('short_text_color') ?: '#303030',
                    'link_color'  => $config->get('short_link_color') ?: '#2da0ce',
                    'btn_color'   => $config->get('short_btn_color') ?: '#FFFFFF',
                    'btn_color_hover' => $config->get('short_btn_color') ?: '#FFFFFF',
                    'btn_background' => $config->get('short_btn_background') ?: '#2da0ce',
                    'btn_background_hover' => $config->get('short_btn_background') ?: '#2da0ce',
                    'btn_border_width' => '0',
                    'btn_border_color' => $config->get('short_btn_background') ?: '#2da0ce',
                    'btn_border_color_hover' => $config->get('short_btn_background') ?: '#2da0ce',
                    'transition_animation' => '.2s',
                    'btn_border_radius' => '.25rem',
                ];
            }
            else {
                $vars = [
                    'font_family' => $config->get('font_family') ?: 'Verdana',
                    'title_color' => $config->get('title_color') ?: '#303030',
                    'text_color'  => $config->get('text_color') ?: '#303030',
                    'link_color'  => $config->get('link_color') ?: '#303030',
                    'btn_color'   => $config->get('btn_color') ?: '#FFFFFF',
                    'btn_color_hover' => $config->get('btn_color_hover') ?: '#FFFFFF',
                    'btn_background' => $config->get('btn_background') ?: '#2da0ce',
                    'btn_background_hover' => $config->get('btn_background_hover') ?: '#0a75a0',
                    'btn_border_width' => $config->get('btn_border_width') ?: '1px',
                    'btn_border_color' => $config->get('btn_border_color') ?: '#2da0ce',
                    'btn_border_color_hover' => $config->get('btn_border_color_hover') ?: '#0a75a0',
                    'transition_animation' => $config->get('transition_animation') ?: '.3s',
                    'btn_border_radius' => $config->get('btn_border_radius') ?: '.25rem',
                ];
            }

            $tpl = file_get_contents($tpl_path);
            $content = strtr($tpl, [
                '<%= font_family %>' => $vars['font_family'],
                '<%= title_color %>' => $vars['title_color'],
                '<%= text_color %>' => $vars['text_color'],
                '<%= link_color %>' => $vars['link_color'],
                '<%= btn_color %>' => $vars['btn_color'],
                '<%= btn_color_hover %>' => $vars['btn_color_hover'],
                '<%= btn_background %>' => $vars['btn_background'],
                '<%= btn_background_hover %>' => $vars['btn_background_hover'],
                '<%= btn_border_width %>' => $vars['btn_border_width'],
                '<%= btn_border_color %>' => $vars['btn_border_color'],
                '<%= btn_border_color_hover %>' => $vars['btn_border_color_hover'],
                '<%= transition_animation %>' => $vars['transition_animation'],
                '<%= btn_border_radius %>' => $vars['btn_border_radius'],
            ]);

            file_put_contents($output_path, $content);
            \Drupal::messenger()->addMessage($this->t('Файл стилей успешно обновлён.'));
        }
        else {
            \Drupal::messenger()->addError($this->t('Шаблон variables.css.tpl не найден.'));
        }

        parent::submitForm($form, $form_state);
    }
}
