<?php

namespace FelixNagel\T3extblog\Service;

/**
 * This file is part of the "t3extblog" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

use FelixNagel\T3extblog\Traits\LoggingTrait;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MailUtility;
use TYPO3\CMS\Extbase\Object\ObjectManagerInterface;
use TYPO3\CMS\Extbase\SignalSlot\Dispatcher;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Core\Context\Context;

/**
 * Handles email sending and templating.
 */
class EmailService implements SingletonInterface
{
    use LoggingTrait;

    /**
     * @var string
     */
    public const TEMPLATE_FOLDER = 'Email';

    /**
     * Extension name.
     */
    protected string $extensionName = 't3extblog';

    protected ObjectManagerInterface $objectManager;

    protected Dispatcher $signalSlotDispatcher;

    protected SettingsService $settingsService;

    protected array $settings = [];

    /**
     * EmailService constructor.
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        Dispatcher $signalSlotDispatcher,
        SettingsService $settingsService
    ) {
        $this->objectManager = $objectManager;
        $this->signalSlotDispatcher = $signalSlotDispatcher;
        $this->settingsService = $settingsService;
    }

    public function initializeObject()
    {
        $this->settings = $this->settingsService->getTypoScriptSettings();
    }

    /**
     * This is the main-function for sending Mails.
     *
     *
     * @return int the number of recipients who were accepted for delivery
     */
    public function sendEmail(array $mailTo, array $mailFrom, string $subject, array $variables, string $templatePath): int
    {
        $this->signalSlotDispatcher->dispatch(
            self::class,
            'sendEmail',
            [&$mailTo, &$mailFrom, &$subject, &$variables, &$templatePath, $this]
        );

        // @extensionScannerIgnoreLine
        return $this->send($mailTo, $mailFrom, $subject, $this->render($variables, $templatePath));
    }

    /**
     * This is the main-function for sending Mails.
     *
     *
     * @return int the number of recipients who were accepted for delivery
     */
    public function send(array $mailTo, array $mailFrom, string $subject, string $emailBody): int
    {
        if (!($mailTo && is_array($mailTo) && GeneralUtility::validEmail(key($mailTo)))) {
            // @extensionScannerIgnoreLine
            $this->getLog()->error('Given mailto email address is invalid.', $mailTo);

            return false;
        }

        if (!($mailFrom && is_array($mailFrom) && GeneralUtility::validEmail(key($mailFrom)))) {
            $mailFrom = MailUtility::getSystemFrom();
        }

        $message = $this->createMailMessage();
        $message
            ->setSubject($subject)
            ->setTo($mailTo)
            ->setFrom($mailFrom);

        $this->setMessageContent($message, $emailBody);

        if (!$this->settings['debug']['disableEmailTransmission']) {
            $message->send();
        }

        $logData = [
            'mailTo' => $mailTo,
            'mailFrom' => $mailFrom,
            'subject' => $subject,
            'emailBody' => $emailBody,
            'isSent' => $message->isSent(),
        ];
        $this->getLog()->dev('Email sent.', $logData);

        return $logData['isSent'];
    }

    /**
     * This functions renders template to use in Mails and Other views.
     *
     * @param array  $variables    Arguments for template
     * @param string $templatePath Choose a template
     */
    public function render(array $variables, string $templatePath = 'Default.txt'): string
    {
        $emailView = $this->getEmailView($templatePath);
        $emailView->assignMultiple($variables);
        $emailView->assignMultiple([
            'timestamp' => GeneralUtility::makeInstance(Context::class)->getPropertyFromAspect('date', 'timestamp'),
            'domain' => GeneralUtility::getIndpEnv('TYPO3_SITE_URL'),
            'settings' => $this->settings,
        ]);

        return $emailView->render();
    }

    /**
     * Create and configure the view.
     *
     * @param string $templateFile Choose a template
     */
    public function getEmailView(string $templateFile): StandaloneView
    {
        $emailView = $this->createStandaloneView();

        $format = pathinfo($templateFile, PATHINFO_EXTENSION);
        $emailView->setFormat($format);
        $emailView->getTemplatePaths()->setFormat($format);

        $emailView->getRenderingContext()->setControllerName(self::TEMPLATE_FOLDER);
        $emailView->setTemplate($templateFile);

        return $emailView;
    }


    protected function createStandaloneView(): StandaloneView
    {
        /* @var $emailView StandaloneView */
        $emailView = $this->objectManager->get(StandaloneView::class);
        $emailView->getRequest()->setPluginName('');
        $emailView->getRequest()->setControllerExtensionName($this->extensionName);

        $this->setViewPaths($emailView);

        return $emailView;
    }

    protected function setViewPaths(StandaloneView $emailView)
    {
        $frameworkConfig = $this->settingsService->getFrameworkSettings();

        if (isset($frameworkConfig['view']['layoutRootPaths'])) {
            $emailView->setLayoutRootPaths($frameworkConfig['view']['layoutRootPaths']);
        }

        if (isset($frameworkConfig['view']['partialRootPaths'])) {
            $emailView->setPartialRootPaths($frameworkConfig['view']['partialRootPaths']);
        }

        if (isset($frameworkConfig['view']['templateRootPaths'])) {
            $emailView->setTemplateRootPaths($frameworkConfig['view']['templateRootPaths']);
        }
    }

    /**
     * Prepare html as plain text.
     *
     *
     */
    protected function preparePlainTextBody(string $html): string
    {
        // Remove style tags
        $output = preg_replace('#<style\b[^>]*>(.*?)<\/style>#s', '', $html);

        // Remove tags and extract url from link tags
        $output = strip_tags(preg_replace('#<a.* href=(?:"|\')(.*)(?:"|\').*>#', '$1', $output));

        // Break lines and clean up white spaces
        $output = MailUtility::breakLinesForEmail($output);

        return preg_replace('#(?:(?:\r\n|\r|\n)\s*){2}#s', "\n\n", $output);
    }


    protected function setMessageContent(MailMessage $message, string $emailBody)
    {
        // Plain text only
        if (strip_tags($emailBody) === $emailBody) {
            $message->text($emailBody);
        } else {
            // Send as HTML and plain text
            $message->html($emailBody);
            $message->text($this->preparePlainTextBody($emailBody));
        }
    }

    /**
     * Create mail message
     *
     */
    protected function createMailMessage(): MailMessage
    {
        return $this->objectManager->get(MailMessage::class);
    }
}
