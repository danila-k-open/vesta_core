<?php

namespace Drupal\domain_verification_upload\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Form to upload domain verification files into the web root (DRUPAL_ROOT).
 */
class DomainVerificationUploadForm extends FormBase {

    protected FileSystemInterface $fileSystem;

    public function __construct(FileSystemInterface $file_system) {
        $this->fileSystem = $file_system;
    }

    public static function create(ContainerInterface $container): self {
        return new static(
            $container->get('file_system'),
        );
    }

    public function getFormId(): string {
        return 'domain_verification_upload_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state): array {
        // Нужно для загрузки файлов.
        $form['#attributes']['enctype'] = 'multipart/form-data';

        $web_root = DRUPAL_ROOT;
        $writable = is_writable($web_root);

        $form['intro'] = [
            '#type' => 'item',
            '#markup' => '<p>Загрузите файл подтверждения владения доменом (например, Yandex Webmaster/Google Search Console ). Файл будет помещён в корневую папку сайта.</p>'
                . ($writable ? '' : '<p><strong style="color:#b00">Внимание:</strong> корневая папка сейчас не доступна для записи PHP. Загрузка не сработает, пока права не будут изменены.</p>'),
        ];

        $form['upload'] = [
            '#type' => 'file',
            '#title' => $this->t('Файл подтверждения'),
            '#description' => $this->t('Разрешенные расширения: html, txt, xml, json. Пример: yandexXXXXXXXXXXXX.html'),
            '#required' => TRUE,
        ];

        $form['overwrite'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Перезаписать существующий'),
            '#default_value' => FALSE,
        ];

        $form['actions'] = [
            '#type' => 'actions',
        ];

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Загрузить'),
            '#disabled' => !$writable,
        ];

        return $form;
    }

    public function validateForm(array &$form, FormStateInterface $form_state): void {
        parent::validateForm($form, $form_state);

        // Валидаторы для file_save_upload.
        $validators = [
            // Ограничиваем расширения, чтобы не дать загрузить исполняемое.
            'file_validate_extensions' => ['html txt xml json'],
            // Ограничим размер (можно увеличить при необходимости).
            'file_validate_size' => [256 * 1024],
        ];

        // Сохраняем во временную область.
        $file = file_save_upload('upload', $validators, 'temporary://', 0, FileSystemInterface::EXISTS_RENAME);
        if (!$file) {
            $form_state->setErrorByName('upload', $this->t('Upload failed. Make sure you selected a valid file.'));
            return;
        }

        // Проверим имя файла на безопасный формат (без путей и странных символов).
        $filename = $file->getFilename();

        // Запрещаем любые слеши/обход путей.
        if ($filename !== basename($filename)) {
            $form_state->setErrorByName('upload', $this->t('Неверное расширение файла'));
            return;
        }

        // Разрешим только [A-Za-z0-9._-] (обычно верификационные файлы такие).
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $filename)) {
            $form_state->setErrorByName('upload', $this->t('Имя файла содержит запрщенные символы. Используйте только буквы, цифры, точки, нижние подчеркивания и тире.'));
            return;
        }

        // Передадим файл в submit.
        $form_state->setValue('uploaded_file_uri', $file->getFileUri());
        $form_state->setValue('uploaded_file_name', $filename);
    }

    public function submitForm(array &$form, FormStateInterface $form_state): void {
        $source_uri = $form_state->getValue('uploaded_file_uri');
        $filename = $form_state->getValue('uploaded_file_name');
        $overwrite = (bool) $form_state->getValue('overwrite');

        $source_path = $this->fileSystem->realpath($source_uri);
        if (!$source_path || !file_exists($source_path)) {
            $this->messenger()->addError($this->t('Временно загруженный файл не существует.'));
            return;
        }

        $destination_path = rtrim(DRUPAL_ROOT, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($destination_path) && !$overwrite) {
            $this->messenger()->addError($this->t('Файл %name уже существуюет в корневой папке. Включить перезапись для загрузки.', [
                '%name' => $filename,
            ]));
            return;
        }

        // Если overwrite включён — перезаписываем.
        if (file_exists($destination_path) && $overwrite) {
            @unlink($destination_path);
        }

        // Копируем из temp в web-root.
        $ok = @copy($source_path, $destination_path);

        // Удалим временный файл.
        @unlink($source_path);

        if (!$ok) {
            $this->messenger()->addError($this->t('Загрузить файл не удалось, проверьте права на запись в папку %path.', [
                '%path' => DRUPAL_ROOT,
            ]));
            return;
        }

        // Поставим права чтения (опционально; зависит от окружения).
        @chmod($destination_path, 0644);

        $link = Link::fromTextAndUrl($this->t('ссылке'), Url::fromUserInput('/' . $filename))->toString();

        $this->messenger()->addStatus($this->t('Загрузка файла %name завершена, проверьте существование файла по @link.', [
            '%name' => $filename,
            '@link' => $link,
        ]));
    }

}
