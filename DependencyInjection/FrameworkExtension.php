<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection;

use Composer\InstalledVersions;
use Doctrine\Common\Annotations\Reader;
use Http\Client\HttpClient;
use phpDocumentor\Reflection\DocBlockFactoryInterface;
use phpDocumentor\Reflection\Types\ContextFactory;
use PhpParser\Parser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface as PsrClockInterface;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerAwareInterface;
use Symfony\Bridge\Monolog\Processor\DebugProcessor;
use Symfony\Bridge\Twig\Extension\CsrfExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\FrameworkBundle\Routing\RouteLoaderInterface;
use Symfony\Bundle\FullStack;
use Symfony\Bundle\MercureBundle\MercureBundle;
use Symfony\Component\Asset\PackageInterface;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\DependencyInjection\CachePoolPass;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\Cache\Marshaller\MarshallerInterface;
use Symfony\Component\Cache\ResettableInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\Config\ResourceCheckerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\EnvVarLoaderInterface;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Dotenv\Command\DebugCommand;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Form\ChoiceList\Factory\CachingFactoryDecorator;
use Symfony\Component\Form\Extension\HtmlSanitizer\Type\TextTypeHtmlSanitizerExtension;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormTypeExtensionInterface;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\BackedEnumValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\UidValueResolver;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\StoreFactory;
use Symfony\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailTransportFactory;
use Symfony\Component\Mailer\Bridge\Infobip\Transport\InfobipTransportFactory as InfobipMailerTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailchimp\Transport\MandrillTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory;
use Symfony\Component\Mailer\Bridge\Mailjet\Transport\MailjetTransportFactory;
use Symfony\Component\Mailer\Bridge\MailPace\Transport\MailPaceTransportFactory;
use Symfony\Component\Mailer\Bridge\OhMySmtp\Transport\OhMySmtpTransportFactory;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkTransportFactory;
use Symfony\Component\Mailer\Bridge\Sendgrid\Transport\SendgridTransportFactory;
use Symfony\Component\Mailer\Bridge\Sendinblue\Transport\SendinblueTransportFactory;
use Symfony\Component\Mailer\Command\MailerTestCommand;
use Symfony\Component\Mailer\EventListener\MessengerTransportListener;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsTransportFactory;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Bridge\Beanstalkd\Transport\BeanstalkdTransportFactory;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransportFactory;
use Symfony\Component\Messenger\Command\StatsCommand;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\RouterContextMiddleware;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\MimeTypeGuesserInterface;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Notifier\Bridge\AllMySms\AllMySmsTransportFactory;
use Symfony\Component\Notifier\Bridge\AmazonSns\AmazonSnsTransportFactory;
use Symfony\Component\Notifier\Bridge\Bandwidth\BandwidthTransportFactory;
use Symfony\Component\Notifier\Bridge\Chatwork\ChatworkTransportFactory;
use Symfony\Component\Notifier\Bridge\Clickatell\ClickatellTransportFactory;
use Symfony\Component\Notifier\Bridge\ContactEveryone\ContactEveryoneTransportFactory;
use Symfony\Component\Notifier\Bridge\Discord\DiscordTransportFactory;
use Symfony\Component\Notifier\Bridge\Engagespot\EngagespotTransportFactory;
use Symfony\Component\Notifier\Bridge\Esendex\EsendexTransportFactory;
use Symfony\Component\Notifier\Bridge\Expo\ExpoTransportFactory;
use Symfony\Component\Notifier\Bridge\FakeChat\FakeChatTransportFactory;
use Symfony\Component\Notifier\Bridge\FakeSms\FakeSmsTransportFactory;
use Symfony\Component\Notifier\Bridge\Firebase\FirebaseTransportFactory;
use Symfony\Component\Notifier\Bridge\FortySixElks\FortySixElksTransportFactory;
use Symfony\Component\Notifier\Bridge\FreeMobile\FreeMobileTransportFactory;
use Symfony\Component\Notifier\Bridge\GatewayApi\GatewayApiTransportFactory;
use Symfony\Component\Notifier\Bridge\Gitter\GitterTransportFactory;
use Symfony\Component\Notifier\Bridge\GoogleChat\GoogleChatTransportFactory;
use Symfony\Component\Notifier\Bridge\Infobip\InfobipTransportFactory;
use Symfony\Component\Notifier\Bridge\Iqsms\IqsmsTransportFactory;
use Symfony\Component\Notifier\Bridge\Isendpro\IsendproTransportFactory;
use Symfony\Component\Notifier\Bridge\KazInfoTeh\KazInfoTehTransportFactory;
use Symfony\Component\Notifier\Bridge\LightSms\LightSmsTransportFactory;
use Symfony\Component\Notifier\Bridge\LineNotify\LineNotifyTransportFactory;
use Symfony\Component\Notifier\Bridge\LinkedIn\LinkedInTransportFactory;
use Symfony\Component\Notifier\Bridge\Mailjet\MailjetTransportFactory as MailjetNotifierTransportFactory;
use Symfony\Component\Notifier\Bridge\Mastodon\MastodonTransportFactory;
use Symfony\Component\Notifier\Bridge\Mattermost\MattermostTransportFactory;
use Symfony\Component\Notifier\Bridge\Mercure\MercureTransportFactory;
use Symfony\Component\Notifier\Bridge\MessageBird\MessageBirdTransport;
use Symfony\Component\Notifier\Bridge\MessageMedia\MessageMediaTransportFactory;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\MicrosoftTeamsTransportFactory;
use Symfony\Component\Notifier\Bridge\Mobyt\MobytTransportFactory;
use Symfony\Component\Notifier\Bridge\Octopush\OctopushTransportFactory;
use Symfony\Component\Notifier\Bridge\OneSignal\OneSignalTransportFactory;
use Symfony\Component\Notifier\Bridge\OrangeSms\OrangeSmsTransportFactory;
use Symfony\Component\Notifier\Bridge\OvhCloud\OvhCloudTransportFactory;
use Symfony\Component\Notifier\Bridge\PagerDuty\PagerDutyTransportFactory;
use Symfony\Component\Notifier\Bridge\Plivo\PlivoTransportFactory;
use Symfony\Component\Notifier\Bridge\RingCentral\RingCentralTransportFactory;
use Symfony\Component\Notifier\Bridge\RocketChat\RocketChatTransportFactory;
use Symfony\Component\Notifier\Bridge\Sendberry\SendberryTransportFactory;
use Symfony\Component\Notifier\Bridge\Sendinblue\SendinblueTransportFactory as SendinblueNotifierTransportFactory;
use Symfony\Component\Notifier\Bridge\Sinch\SinchTransportFactory;
use Symfony\Component\Notifier\Bridge\Slack\SlackTransportFactory;
use Symfony\Component\Notifier\Bridge\Sms77\Sms77TransportFactory;
use Symfony\Component\Notifier\Bridge\Smsapi\SmsapiTransportFactory;
use Symfony\Component\Notifier\Bridge\SmsBiuras\SmsBiurasTransportFactory;
use Symfony\Component\Notifier\Bridge\Smsc\SmscTransportFactory;
use Symfony\Component\Notifier\Bridge\SmsFactor\SmsFactorTransportFactory;
use Symfony\Component\Notifier\Bridge\SpotHit\SpotHitTransportFactory;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramTransportFactory;
use Symfony\Component\Notifier\Bridge\Telnyx\TelnyxTransportFactory;
use Symfony\Component\Notifier\Bridge\Termii\TermiiTransportFactory;
use Symfony\Component\Notifier\Bridge\TurboSms\TurboSmsTransport;
use Symfony\Component\Notifier\Bridge\Twilio\TwilioTransportFactory;
use Symfony\Component\Notifier\Bridge\Twitter\TwitterTransportFactory;
use Symfony\Component\Notifier\Bridge\Vonage\VonageTransportFactory;
use Symfony\Component\Notifier\Bridge\Yunpian\YunpianTransportFactory;
use Symfony\Component\Notifier\Bridge\Zendesk\ZendeskTransportFactory;
use Symfony\Component\Notifier\Bridge\Zulip\ZulipTransportFactory;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\TexterInterface;
use Symfony\Component\Notifier\Transport\TransportFactoryInterface as NotifierTransportFactoryInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyInitializableExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyWriteInfoExtractorInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Component\Routing\Loader\Psr4DirectoryLoader;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Semaphore\PersistingStoreInterface as SemaphoreStoreInterface;
use Symfony\Component\Semaphore\Semaphore;
use Symfony\Component\Semaphore\SemaphoreFactory;
use Symfony\Component\Semaphore\Store\StoreFactory as SemaphoreStoreFactory;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Mapping\Loader\XmlFileLoader;
use Symfony\Component\Serializer\Mapping\Loader\YamlFileLoader;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\UnwrappingDenormalizer;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\String\LazyString;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Translation\Bridge\Crowdin\CrowdinProviderFactory;
use Symfony\Component\Translation\Bridge\Loco\LocoProviderFactory;
use Symfony\Component\Translation\Bridge\Lokalise\LokaliseProviderFactory;
use Symfony\Component\Translation\Command\XliffLintCommand as BaseXliffLintCommand;
use Symfony\Component\Translation\Extractor\PhpAstExtractor;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Component\Translation\PseudoLocalizationTranslator;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Uid\Factory\UuidFactory;
use Symfony\Component\Uid\UuidV4;
use Symfony\Component\Validator\Constraints\WhenValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Mapping\Loader\PropertyInfoLoader;
use Symfony\Component\Validator\ObjectInitializerInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\WebLink\HttpHeaderSerializer;
use Symfony\Component\Workflow;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Yaml\Command\LintCommand as BaseYamlLintCommand;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\CallbackInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;

/**
 * Process the configuration and prepare the dependency injection container with
 * parameters and services.
 */
class FrameworkExtension extends Extension
{
    private array $configsEnabled = [];

    /**
     * Responds to the app.config configuration parameter.
     *
     * @throws LogicException
     *
     * @return void
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));

        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('symfony/symfony') && 'symfony/symfony' !== (InstalledVersions::getRootPackage()['name'] ?? '')) {
            trigger_deprecation('symfony/symfony', '6.1', 'Requiring the "symfony/symfony" package is deprecated; replace it with standalone components instead.');
        }

        $loader->load('web.php');

        if (!class_exists(BackedEnumValueResolver::class)) {
            $container->removeDefinition('argument_resolver.backed_enum_resolver');
        }

        $loader->load('services.php');
        $loader->load('fragment_renderer.php');
        $loader->load('error_renderer.php');

        if (!ContainerBuilder::willBeAvailable('symfony/clock', ClockInterface::class, ['symfony/framework-bundle'])) {
            $container->removeDefinition('clock');
            $container->removeAlias(ClockInterface::class);
            $container->removeAlias(PsrClockInterface::class);
        }

        $container->registerAliasForArgument('parameter_bag', PsrContainerInterface::class);

        if ($this->hasConsole()) {
            $loader->load('console.php');

            if (!class_exists(BaseXliffLintCommand::class)) {
                $container->removeDefinition('console.command.xliff_lint');
            }
            if (!class_exists(BaseYamlLintCommand::class)) {
                $container->removeDefinition('console.command.yaml_lint');
            }

            if (!class_exists(DebugCommand::class)) {
                $container->removeDefinition('console.command.dotenv_debug');
            }
        }

        // Load Cache configuration first as it is used by other components
        $loader->load('cache.php');

        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        // warmup config enabled
        $this->readConfigEnabled('annotations', $container, $config['annotations']);
        $this->readConfigEnabled('translator', $container, $config['translator']);
        $this->readConfigEnabled('property_access', $container, $config['property_access']);
        $this->readConfigEnabled('profiler', $container, $config['profiler']);

        // A translator must always be registered (as support is included by
        // default in the Form and Validator component). If disabled, an identity
        // translator will be used and everything will still work as expected.
        if ($this->readConfigEnabled('translator', $container, $config['translator']) || $this->readConfigEnabled('form', $container, $config['form']) || $this->readConfigEnabled('validation', $container, $config['validation'])) {
            if (!class_exists(Translator::class) && $this->readConfigEnabled('translator', $container, $config['translator'])) {
                throw new LogicException('Translation support cannot be enabled as the Translation component is not installed. Try running "composer require symfony/translation".');
            }

            if (class_exists(Translator::class)) {
                $loader->load('identity_translator.php');
            }
        }

        $container->getDefinition('locale_listener')->replaceArgument(3, $config['set_locale_from_accept_language']);
        $container->getDefinition('response_listener')->replaceArgument(1, $config['set_content_language_from_locale']);
        $container->getDefinition('http_kernel')->replaceArgument(4, $config['handle_all_throwables'] ?? false);

        // If the slugger is used but the String component is not available, we should throw an error
        if (!ContainerBuilder::willBeAvailable('symfony/string', SluggerInterface::class, ['symfony/framework-bundle'])) {
            $container->register('slugger', 'stdClass')
                ->addError('You cannot use the "slugger" service since the String component is not installed. Try running "composer require symfony/string".');
        } else {
            if (!ContainerBuilder::willBeAvailable('symfony/translation', LocaleAwareInterface::class, ['symfony/framework-bundle'])) {
                $container->register('slugger', 'stdClass')
                    ->addError('You cannot use the "slugger" service since the Translation contracts are not installed. Try running "composer require symfony/translation".');
            }

            if (!\extension_loaded('intl') && !\defined('PHPUNIT_COMPOSER_INSTALL')) {
                trigger_deprecation('', '', 'Please install the "intl" PHP extension for best performance.');
            }
        }

        if (isset($config['secret'])) {
            $container->setParameter('kernel.secret', $config['secret']);
        }

        $container->setParameter('kernel.http_method_override', $config['http_method_override']);
        $container->setParameter('kernel.trust_x_sendfile_type_header', $config['trust_x_sendfile_type_header']);
        $container->setParameter('kernel.trusted_hosts', $config['trusted_hosts']);
        $container->setParameter('kernel.default_locale', $config['default_locale']);
        $container->setParameter('kernel.enabled_locales', $config['enabled_locales']);
        $container->setParameter('kernel.error_controller', $config['error_controller']);

        if (($config['trusted_proxies'] ?? false) && ($config['trusted_headers'] ?? false)) {
            $container->setParameter('kernel.trusted_proxies', $config['trusted_proxies']);
            $container->setParameter('kernel.trusted_headers', $this->resolveTrustedHeaders($config['trusted_headers']));
        }

        if (!$container->hasParameter('debug.file_link_format')) {
            $container->setParameter('debug.file_link_format', $config['ide']);
        }

        if (!empty($config['test'])) {
            $loader->load('test.php');

            if (!class_exists(AbstractBrowser::class)) {
                $container->removeDefinition('test.client');
            }
        }

        if ($this->readConfigEnabled('request', $container, $config['request'])) {
            $this->registerRequestConfiguration($config['request'], $container, $loader);
        }

        if ($this->readConfigEnabled('assets', $container, $config['assets'])) {
            if (!class_exists(\Symfony\Component\Asset\Package::class)) {
                throw new LogicException('Asset support cannot be enabled as the Asset component is not installed. Try running "composer require symfony/asset".');
            }

            $this->registerAssetsConfiguration($config['assets'], $container, $loader);
        }

        if ($this->readConfigEnabled('http_client', $container, $config['http_client'])) {
            $this->registerHttpClientConfiguration($config['http_client'], $container, $loader);
        }

        if ($this->readConfigEnabled('mailer', $container, $config['mailer'])) {
            $this->registerMailerConfiguration($config['mailer'], $container, $loader);

            if (!$this->hasConsole() || !class_exists(MailerTestCommand::class)) {
                $container->removeDefinition('console.command.mailer_test');
            }
        }

        $propertyInfoEnabled = $this->readConfigEnabled('property_info', $container, $config['property_info']);
        $this->registerHttpCacheConfiguration($config['http_cache'], $container, $config['http_method_override']);
        $this->registerEsiConfiguration($config['esi'], $container, $loader);
        $this->registerSsiConfiguration($config['ssi'], $container, $loader);
        $this->registerFragmentsConfiguration($config['fragments'], $container, $loader);
        $this->registerTranslatorConfiguration($config['translator'], $container, $loader, $config['default_locale'], $config['enabled_locales']);
        $this->registerWorkflowConfiguration($config['workflows'], $container, $loader);
        $this->registerDebugConfiguration($config['php_errors'], $container, $loader);
        $this->registerRouterConfiguration($config['router'], $container, $loader, $config['enabled_locales']);
        $this->registerAnnotationsConfiguration($config['annotations'], $container, $loader);
        $this->registerPropertyAccessConfiguration($config['property_access'], $container, $loader);
        $this->registerSecretsConfiguration($config['secrets'], $container, $loader);

        $container->getDefinition('exception_listener')->replaceArgument(3, $config['exceptions']);

        if ($this->readConfigEnabled('serializer', $container, $config['serializer'])) {
            if (!class_exists(\Symfony\Component\Serializer\Serializer::class)) {
                throw new LogicException('Serializer support cannot be enabled as the Serializer component is not installed. Try running "composer require symfony/serializer-pack".');
            }

            $this->registerSerializerConfiguration($config['serializer'], $container, $loader);
        }

        if ($propertyInfoEnabled) {
            $this->registerPropertyInfoConfiguration($container, $loader);
        }

        if ($this->readConfigEnabled('lock', $container, $config['lock'])) {
            $this->registerLockConfiguration($config['lock'], $container, $loader);
        }

        if ($this->readConfigEnabled('semaphore', $container, $config['semaphore'])) {
            $this->registerSemaphoreConfiguration($config['semaphore'], $container, $loader);
        }

        if ($this->readConfigEnabled('rate_limiter', $container, $config['rate_limiter'])) {
            if (!interface_exists(LimiterInterface::class)) {
                throw new LogicException('Rate limiter support cannot be enabled as the RateLimiter component is not installed. Try running "composer require symfony/rate-limiter".');
            }

            $this->registerRateLimiterConfiguration($config['rate_limiter'], $container, $loader);
        }

        if ($this->readConfigEnabled('web_link', $container, $config['web_link'])) {
            if (!class_exists(HttpHeaderSerializer::class)) {
                throw new LogicException('WebLink support cannot be enabled as the WebLink component is not installed. Try running "composer require symfony/weblink".');
            }

            $loader->load('web_link.php');
        }

        if ($this->readConfigEnabled('uid', $container, $config['uid'])) {
            if (!class_exists(UuidFactory::class)) {
                throw new LogicException('Uid support cannot be enabled as the Uid component is not installed. Try running "composer require symfony/uid".');
            }

            $this->registerUidConfiguration($config['uid'], $container, $loader);
        } else {
            $container->removeDefinition('argument_resolver.uid');
        }

        // register cache before session so both can share the connection services
        $this->registerCacheConfiguration($config['cache'], $container);

        if ($this->readConfigEnabled('session', $container, $config['session'])) {
            if (!\extension_loaded('session')) {
                throw new LogicException('Session support cannot be enabled as the session extension is not installed. See https://php.net/session.installation for instructions.');
            }

            $this->registerSessionConfiguration($config['session'], $container, $loader);
            if (!empty($config['test'])) {
                // test listener will replace the existing session listener
                // as we are aliasing to avoid duplicated registered events
                $container->setAlias('session_listener', 'test.session.listener');
            }
        } elseif (!empty($config['test'])) {
            $container->removeDefinition('test.session.listener');
        }

        // csrf depends on session being registered
        if (null === $config['csrf_protection']['enabled']) {
            $this->writeConfigEnabled('csrf_protection', $this->readConfigEnabled('session', $container, $config['session']) && !class_exists(FullStack::class) && ContainerBuilder::willBeAvailable('symfony/security-csrf', CsrfTokenManagerInterface::class, ['symfony/framework-bundle']), $config['csrf_protection']);
        }
        $this->registerSecurityCsrfConfiguration($config['csrf_protection'], $container, $loader);

        // form depends on csrf being registered
        if ($this->readConfigEnabled('form', $container, $config['form'])) {
            if (!class_exists(Form::class)) {
                throw new LogicException('Form support cannot be enabled as the Form component is not installed. Try running "composer require symfony/form".');
            }

            $this->registerFormConfiguration($config, $container, $loader);

            if (ContainerBuilder::willBeAvailable('symfony/validator', Validation::class, ['symfony/framework-bundle', 'symfony/form'])) {
                $this->writeConfigEnabled('validation', true, $config['validation']);
            } else {
                $container->setParameter('validator.translation_domain', 'validators');

                $container->removeDefinition('form.type_extension.form.validator');
                $container->removeDefinition('form.type_guesser.validator');
            }
            if (!$this->readConfigEnabled('html_sanitizer', $container, $config['html_sanitizer']) || !class_exists(TextTypeHtmlSanitizerExtension::class)) {
                $container->removeDefinition('form.type_extension.form.html_sanitizer');
            }
        } else {
            $container->removeDefinition('console.command.form_debug');
        }

        // validation depends on form, annotations being registered
        $this->registerValidationConfiguration($config['validation'], $container, $loader, $propertyInfoEnabled);

        // messenger depends on validation being registered
        if ($this->readConfigEnabled('messenger', $container, $config['messenger'])) {
            $this->registerMessengerConfiguration($config['messenger'], $container, $loader, $config['validation']);
        } else {
            $container->removeDefinition('console.command.messenger_consume_messages');
            $container->removeDefinition('console.command.messenger_stats');
            $container->removeDefinition('console.command.messenger_debug');
            $container->removeDefinition('console.command.messenger_stop_workers');
            $container->removeDefinition('console.command.messenger_setup_transports');
            $container->removeDefinition('console.command.messenger_failed_messages_retry');
            $container->removeDefinition('console.command.messenger_failed_messages_show');
            $container->removeDefinition('console.command.messenger_failed_messages_remove');
            $container->removeDefinition('cache.messenger.restart_workers_signal');

            if ($container->hasDefinition('messenger.transport.amqp.factory') && !class_exists(AmqpTransportFactory::class)) {
                if (class_exists(\Symfony\Component\Messenger\Transport\AmqpExt\AmqpTransportFactory::class)) {
                    $container->getDefinition('messenger.transport.amqp.factory')
                        ->setClass(\Symfony\Component\Messenger\Transport\AmqpExt\AmqpTransportFactory::class)
                        ->addTag('messenger.transport_factory');
                } else {
                    $container->removeDefinition('messenger.transport.amqp.factory');
                }
            }

            if ($container->hasDefinition('messenger.transport.redis.factory') && !class_exists(RedisTransportFactory::class)) {
                if (class_exists(\Symfony\Component\Messenger\Transport\RedisExt\RedisTransportFactory::class)) {
                    $container->getDefinition('messenger.transport.redis.factory')
                        ->setClass(\Symfony\Component\Messenger\Transport\RedisExt\RedisTransportFactory::class)
                        ->addTag('messenger.transport_factory');
                } else {
                    $container->removeDefinition('messenger.transport.redis.factory');
                }
            }
        }

        // notifier depends on messenger, mailer being registered
        if ($this->readConfigEnabled('notifier', $container, $config['notifier'])) {
            $this->registerNotifierConfiguration($config['notifier'], $container, $loader);
        }

        // profiler depends on form, validation, translation, messenger, mailer, http-client, notifier, serializer being registered
        $this->registerProfilerConfiguration($config['profiler'], $container, $loader);

        if ($this->readConfigEnabled('html_sanitizer', $container, $config['html_sanitizer'])) {
            if (!class_exists(HtmlSanitizerConfig::class)) {
                throw new LogicException('HtmlSanitizer support cannot be enabled as the HtmlSanitizer component is not installed. Try running "composer require symfony/html-sanitizer".');
            }

            $this->registerHtmlSanitizerConfiguration($config['html_sanitizer'], $container, $loader);
        }

        $this->addAnnotatedClassesToCompile([
            '**\\Controller\\',
            '**\\Entity\\',

            // Added explicitly so that we don't rely on the class map being dumped to make it work
            AbstractController::class,
        ]);

        if (ContainerBuilder::willBeAvailable('symfony/mime', MimeTypes::class, ['symfony/framework-bundle'])) {
            $loader->load('mime_type.php');
        }

        $container->registerForAutoconfiguration(PackageInterface::class)
            ->addTag('assets.package');
        $container->registerForAutoconfiguration(Command::class)
            ->addTag('console.command');
        $container->registerForAutoconfiguration(ResourceCheckerInterface::class)
            ->addTag('config_cache.resource_checker');
        $container->registerForAutoconfiguration(EnvVarLoaderInterface::class)
            ->addTag('container.env_var_loader');
        $container->registerForAutoconfiguration(EnvVarProcessorInterface::class)
            ->addTag('container.env_var_processor');
        $container->registerForAutoconfiguration(CallbackInterface::class)
            ->addTag('container.reversible');
        $container->registerForAutoconfiguration(ServiceLocator::class)
            ->addTag('container.service_locator');
        $container->registerForAutoconfiguration(ServiceSubscriberInterface::class)
            ->addTag('container.service_subscriber');
        $container->registerForAutoconfiguration(ArgumentValueResolverInterface::class)
            ->addTag('controller.argument_value_resolver');
        $container->registerForAutoconfiguration(ValueResolverInterface::class)
            ->addTag('controller.argument_value_resolver');
        $container->registerForAutoconfiguration(AbstractController::class)
            ->addTag('controller.service_arguments');
        $container->registerForAutoconfiguration(DataCollectorInterface::class)
            ->addTag('data_collector');
        $container->registerForAutoconfiguration(FormTypeInterface::class)
            ->addTag('form.type');
        $container->registerForAutoconfiguration(FormTypeGuesserInterface::class)
            ->addTag('form.type_guesser');
        $container->registerForAutoconfiguration(FormTypeExtensionInterface::class)
            ->addTag('form.type_extension');
        $container->registerForAutoconfiguration(CacheClearerInterface::class)
            ->addTag('kernel.cache_clearer');
        $container->registerForAutoconfiguration(CacheWarmerInterface::class)
            ->addTag('kernel.cache_warmer');
        $container->registerForAutoconfiguration(EventDispatcherInterface::class)
            ->addTag('event_dispatcher.dispatcher');
        $container->registerForAutoconfiguration(EventSubscriberInterface::class)
            ->addTag('kernel.event_subscriber');
        $container->registerForAutoconfiguration(LocaleAwareInterface::class)
            ->addTag('kernel.locale_aware');
        $container->registerForAutoconfiguration(ResetInterface::class)
            ->addTag('kernel.reset', ['method' => 'reset']);

        if (!interface_exists(MarshallerInterface::class)) {
            $container->registerForAutoconfiguration(ResettableInterface::class)
                ->addTag('kernel.reset', ['method' => 'reset']);
        }

        $container->registerForAutoconfiguration(PropertyListExtractorInterface::class)
            ->addTag('property_info.list_extractor');
        $container->registerForAutoconfiguration(PropertyTypeExtractorInterface::class)
            ->addTag('property_info.type_extractor');
        $container->registerForAutoconfiguration(PropertyDescriptionExtractorInterface::class)
            ->addTag('property_info.description_extractor');
        $container->registerForAutoconfiguration(PropertyAccessExtractorInterface::class)
            ->addTag('property_info.access_extractor');
        $container->registerForAutoconfiguration(PropertyInitializableExtractorInterface::class)
            ->addTag('property_info.initializable_extractor');
        $container->registerForAutoconfiguration(EncoderInterface::class)
            ->addTag('serializer.encoder');
        $container->registerForAutoconfiguration(DecoderInterface::class)
            ->addTag('serializer.encoder');
        $container->registerForAutoconfiguration(NormalizerInterface::class)
            ->addTag('serializer.normalizer');
        $container->registerForAutoconfiguration(DenormalizerInterface::class)
            ->addTag('serializer.normalizer');
        $container->registerForAutoconfiguration(ConstraintValidatorInterface::class)
            ->addTag('validator.constraint_validator');
        $container->registerForAutoconfiguration(ObjectInitializerInterface::class)
            ->addTag('validator.initializer');
        $container->registerForAutoconfiguration(MessageHandlerInterface::class)
            ->addTag('messenger.message_handler');
        $container->registerForAutoconfiguration(BatchHandlerInterface::class)
            ->addTag('messenger.message_handler');
        $container->registerForAutoconfiguration(TransportFactoryInterface::class)
            ->addTag('messenger.transport_factory');
        $container->registerForAutoconfiguration(MimeTypeGuesserInterface::class)
            ->addTag('mime.mime_type_guesser');
        $container->registerForAutoconfiguration(LoggerAwareInterface::class)
            ->addMethodCall('setLogger', [new Reference('logger')]);

        $container->registerAttributeForAutoconfiguration(AsEventListener::class, static function (ChildDefinition $definition, AsEventListener $attribute, \ReflectionClass|\ReflectionMethod $reflector) {
            $tagAttributes = get_object_vars($attribute);
            if ($reflector instanceof \ReflectionMethod) {
                if (isset($tagAttributes['method'])) {
                    throw new LogicException(sprintf('AsEventListener attribute cannot declare a method on "%s::%s()".', $reflector->class, $reflector->name));
                }
                $tagAttributes['method'] = $reflector->getName();
            }
            $definition->addTag('kernel.event_listener', $tagAttributes);
        });
        $container->registerAttributeForAutoconfiguration(AsController::class, static function (ChildDefinition $definition, AsController $attribute): void {
            $definition->addTag('controller.service_arguments');
        });

        $container->registerAttributeForAutoconfiguration(AsMessageHandler::class, static function (ChildDefinition $definition, AsMessageHandler $attribute, \ReflectionClass|\ReflectionMethod $reflector): void {
            $tagAttributes = get_object_vars($attribute);
            $tagAttributes['from_transport'] = $tagAttributes['fromTransport'];
            unset($tagAttributes['fromTransport']);
            if ($reflector instanceof \ReflectionMethod) {
                if (isset($tagAttributes['method'])) {
                    throw new LogicException(sprintf('AsMessageHandler attribute cannot declare a method on "%s::%s()".', $reflector->class, $reflector->name));
                }
                $tagAttributes['method'] = $reflector->getName();
            }
            $definition->addTag('messenger.message_handler', $tagAttributes);
        });

        if (!$container->getParameter('kernel.debug')) {
            // remove tagged iterator argument for resource checkers
            $container->getDefinition('config_cache_factory')->setArguments([]);
        }

        if (!$config['disallow_search_engine_index'] ?? false) {
            $container->removeDefinition('disallow_search_engine_index_response_listener');
        }

        $container->registerForAutoconfiguration(RouteLoaderInterface::class)
            ->addTag('routing.route_loader');

        $container->setParameter('container.behavior_describing_tags', [
            'annotations.cached_reader',
            'container.do_not_inline',
            'container.service_locator',
            'container.service_subscriber',
            'kernel.event_subscriber',
            'kernel.event_listener',
            'kernel.locale_aware',
            'kernel.reset',
        ]);
    }

    public function getConfiguration(array $config, ContainerBuilder $container): ?ConfigurationInterface
    {
        return new Configuration($container->getParameter('kernel.debug'));
    }

    protected function hasConsole(): bool
    {
        return class_exists(Application::class);
    }

    private function registerFormConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('form.php');

        if (null === $config['form']['csrf_protection']['enabled']) {
            $this->writeConfigEnabled('form.csrf_protection', $config['csrf_protection']['enabled'], $config['form']['csrf_protection']);
        }

        if ($this->readConfigEnabled('form.csrf_protection', $container, $config['form']['csrf_protection'])) {
            if (!$container->hasDefinition('security.csrf.token_generator')) {
                throw new \LogicException('To use form CSRF protection, "framework.csrf_protection" must be enabled.');
            }

            $loader->load('form_csrf.php');

            $container->setParameter('form.type_extension.csrf.enabled', true);
            $container->setParameter('form.type_extension.csrf.field_name', $config['form']['csrf_protection']['field_name']);
        } else {
            $container->setParameter('form.type_extension.csrf.enabled', false);
        }

        if (!ContainerBuilder::willBeAvailable('symfony/translation', Translator::class, ['symfony/framework-bundle', 'symfony/form'])) {
            $container->removeDefinition('form.type_extension.upload.validator');
        }
        if (!method_exists(CachingFactoryDecorator::class, 'reset')) {
            $container->getDefinition('form.choice_list_factory.cached')
                ->clearTag('kernel.reset')
            ;
        }
    }

    private function registerHttpCacheConfiguration(array $config, ContainerBuilder $container, bool $httpMethodOverride): void
    {
        $options = $config;
        unset($options['enabled']);

        if (!$options['private_headers']) {
            unset($options['private_headers']);
        }

        $container->getDefinition('http_cache')
            ->setPublic($config['enabled'])
            ->replaceArgument(3, $options);

        if ($httpMethodOverride) {
            $container->getDefinition('http_cache')
                  ->addArgument((new Definition('void'))
                      ->setFactory([Request::class, 'enableHttpMethodParameterOverride'])
                  );
        }
    }

    private function registerEsiConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!$this->readConfigEnabled('esi', $container, $config)) {
            $container->removeDefinition('fragment.renderer.esi');

            return;
        }

        $loader->load('esi.php');
    }

    private function registerSsiConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!$this->readConfigEnabled('ssi', $container, $config)) {
            $container->removeDefinition('fragment.renderer.ssi');

            return;
        }

        $loader->load('ssi.php');
    }

    private function registerFragmentsConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!$this->readConfigEnabled('fragments', $container, $config)) {
            $container->removeDefinition('fragment.renderer.hinclude');

            return;
        }

        $container->setParameter('fragment.renderer.hinclude.global_template', $config['hinclude_default_template']);

        $loader->load('fragment_listener.php');
        $container->setParameter('fragment.path', $config['path']);
    }

    private function registerProfilerConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!$this->readConfigEnabled('profiler', $container, $config)) {
            // this is needed for the WebProfiler to work even if the profiler is disabled
            $container->setParameter('data_collector.templates', []);

            return;
        }

        $loader->load('profiling.php');
        $loader->load('collectors.php');
        $loader->load('cache_debug.php');

        if ($this->isInitializedConfigEnabled('form')) {
            $loader->load('form_debug.php');
        }

        if ($this->isInitializedConfigEnabled('validation')) {
            $loader->load('validator_debug.php');
        }

        if ($this->isInitializedConfigEnabled('translator')) {
            $loader->load('translation_debug.php');

            $container->getDefinition('translator.data_collector')->setDecoratedService('translator');
        }

        if ($this->isInitializedConfigEnabled('messenger')) {
            $loader->load('messenger_debug.php');
        }

        if ($this->isInitializedConfigEnabled('mailer')) {
            $loader->load('mailer_debug.php');
        }

        if ($this->isInitializedConfigEnabled('http_client')) {
            $loader->load('http_client_debug.php');
        }

        if ($this->isInitializedConfigEnabled('notifier')) {
            $loader->load('notifier_debug.php');
        }

        if ($this->isInitializedConfigEnabled('serializer') && $config['collect_serializer_data']) {
            $loader->load('serializer_debug.php');
        }

        $container->setParameter('profiler_listener.only_exceptions', $config['only_exceptions']);
        $container->setParameter('profiler_listener.only_main_requests', $config['only_main_requests']);

        // Choose storage class based on the DSN
        [$class] = explode(':', $config['dsn'], 2);
        if ('file' !== $class) {
            throw new \LogicException(sprintf('Driver "%s" is not supported for the profiler.', $class));
        }

        $container->setParameter('profiler.storage.dsn', $config['dsn']);

        $container->getDefinition('profiler')
            ->addArgument($config['collect'])
            ->addTag('kernel.reset', ['method' => 'reset']);

        $container->getDefinition('profiler_listener')
            ->addArgument($config['collect_parameter']);
    }

    private function registerWorkflowConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!$config['enabled']) {
            $container->removeDefinition('console.command.workflow_dump');

            return;
        }

        if (!class_exists(Workflow\Workflow::class)) {
            throw new LogicException('Workflow support cannot be enabled as the Workflow component is not installed. Try running "composer require symfony/workflow".');
        }

        $loader->load('workflow.php');

        $registryDefinition = $container->getDefinition('.workflow.registry');

        foreach ($config['workflows'] as $name => $workflow) {
            $type = $workflow['type'];
            $workflowId = sprintf('%s.%s', $type, $name);

            // Process Metadata (workflow + places (transition is done in the "create transition" block))
            $metadataStoreDefinition = new Definition(Workflow\Metadata\InMemoryMetadataStore::class, [[], [], null]);
            if ($workflow['metadata']) {
                $metadataStoreDefinition->replaceArgument(0, $workflow['metadata']);
            }
            $placesMetadata = [];
            foreach ($workflow['places'] as $place) {
                if ($place['metadata']) {
                    $placesMetadata[$place['name']] = $place['metadata'];
                }
            }
            if ($placesMetadata) {
                $metadataStoreDefinition->replaceArgument(1, $placesMetadata);
            }

            // Create transitions
            $transitions = [];
            $guardsConfiguration = [];
            $transitionsMetadataDefinition = new Definition(\SplObjectStorage::class);
            // Global transition counter per workflow
            $transitionCounter = 0;
            foreach ($workflow['transitions'] as $transition) {
                if ('workflow' === $type) {
                    $transitionDefinition = new Definition(Workflow\Transition::class, [$transition['name'], $transition['from'], $transition['to']]);
                    $transitionDefinition->setPublic(false);
                    $transitionId = sprintf('.%s.transition.%s', $workflowId, $transitionCounter++);
                    $container->setDefinition($transitionId, $transitionDefinition);
                    $transitions[] = new Reference($transitionId);
                    if (isset($transition['guard'])) {
                        $configuration = new Definition(Workflow\EventListener\GuardExpression::class);
                        $configuration->addArgument(new Reference($transitionId));
                        $configuration->addArgument($transition['guard']);
                        $configuration->setPublic(false);
                        $eventName = sprintf('workflow.%s.guard.%s', $name, $transition['name']);
                        $guardsConfiguration[$eventName][] = $configuration;
                    }
                    if ($transition['metadata']) {
                        $transitionsMetadataDefinition->addMethodCall('attach', [
                            new Reference($transitionId),
                            $transition['metadata'],
                        ]);
                    }
                } elseif ('state_machine' === $type) {
                    foreach ($transition['from'] as $from) {
                        foreach ($transition['to'] as $to) {
                            $transitionDefinition = new Definition(Workflow\Transition::class, [$transition['name'], $from, $to]);
                            $transitionDefinition->setPublic(false);
                            $transitionId = sprintf('.%s.transition.%s', $workflowId, $transitionCounter++);
                            $container->setDefinition($transitionId, $transitionDefinition);
                            $transitions[] = new Reference($transitionId);
                            if (isset($transition['guard'])) {
                                $configuration = new Definition(Workflow\EventListener\GuardExpression::class);
                                $configuration->addArgument(new Reference($transitionId));
                                $configuration->addArgument($transition['guard']);
                                $configuration->setPublic(false);
                                $eventName = sprintf('workflow.%s.guard.%s', $name, $transition['name']);
                                $guardsConfiguration[$eventName][] = $configuration;
                            }
                            if ($transition['metadata']) {
                                $transitionsMetadataDefinition->addMethodCall('attach', [
                                    new Reference($transitionId),
                                    $transition['metadata'],
                                ]);
                            }
                        }
                    }
                }
            }
            $metadataStoreDefinition->replaceArgument(2, $transitionsMetadataDefinition);
            $container->setDefinition(sprintf('%s.metadata_store', $workflowId), $metadataStoreDefinition);

            // Create places
            $places = array_column($workflow['places'], 'name');
            $initialMarking = $workflow['initial_marking'] ?? [];

            // Create a Definition
            $definitionDefinition = new Definition(Workflow\Definition::class);
            $definitionDefinition->setPublic(false);
            $definitionDefinition->addArgument($places);
            $definitionDefinition->addArgument($transitions);
            $definitionDefinition->addArgument($initialMarking);
            $definitionDefinition->addArgument(new Reference(sprintf('%s.metadata_store', $workflowId)));

            // Create MarkingStore
            if (isset($workflow['marking_store']['type'])) {
                $markingStoreDefinition = new ChildDefinition('workflow.marking_store.method');
                $markingStoreDefinition->setArguments([
                    'state_machine' === $type, // single state
                    $workflow['marking_store']['property'],
                ]);
            } elseif (isset($workflow['marking_store']['service'])) {
                $markingStoreDefinition = new Reference($workflow['marking_store']['service']);
            }

            // Create Workflow
            $workflowDefinition = new ChildDefinition(sprintf('%s.abstract', $type));
            $workflowDefinition->replaceArgument(0, new Reference(sprintf('%s.definition', $workflowId)));
            $workflowDefinition->replaceArgument(1, $markingStoreDefinition ?? null);
            $workflowDefinition->replaceArgument(3, $name);
            $workflowDefinition->replaceArgument(4, $workflow['events_to_dispatch']);

            $workflowDefinition->addTag('workflow', ['name' => $name]);
            if ('workflow' === $type) {
                $workflowDefinition->addTag('workflow.workflow', ['name' => $name]);
            } elseif ('state_machine' === $type) {
                $workflowDefinition->addTag('workflow.state_machine', ['name' => $name]);
            }

            // Store to container
            $container->setDefinition($workflowId, $workflowDefinition);
            $container->setDefinition(sprintf('%s.definition', $workflowId), $definitionDefinition);
            $container->registerAliasForArgument($workflowId, WorkflowInterface::class, $name.'.'.$type);
            $container->registerAliasForArgument($workflowId, WorkflowInterface::class, $name);

            // Validate Workflow
            if ('state_machine' === $workflow['type']) {
                $validator = new Workflow\Validator\StateMachineValidator();
            } else {
                $validator = new Workflow\Validator\WorkflowValidator();
            }

            $trs = array_map(fn (Reference $ref): Workflow\Transition => $container->get((string) $ref), $transitions);
            $realDefinition = new Workflow\Definition($places, $trs, $initialMarking);
            $validator->validate($realDefinition, $name);

            // Add workflow to Registry
            if ($workflow['supports']) {
                foreach ($workflow['supports'] as $supportedClassName) {
                    $strategyDefinition = new Definition(Workflow\SupportStrategy\InstanceOfSupportStrategy::class, [$supportedClassName]);
                    $strategyDefinition->setPublic(false);
                    $registryDefinition->addMethodCall('addWorkflow', [new Reference($workflowId), $strategyDefinition]);
                }
            } elseif (isset($workflow['support_strategy'])) {
                $registryDefinition->addMethodCall('addWorkflow', [new Reference($workflowId), new Reference($workflow['support_strategy'])]);
            }

            // Enable the AuditTrail
            if ($workflow['audit_trail']['enabled']) {
                $listener = new Definition(Workflow\EventListener\AuditTrailListener::class);
                $listener->addTag('monolog.logger', ['channel' => 'workflow']);
                $listener->addTag('kernel.event_listener', ['event' => sprintf('workflow.%s.leave', $name), 'method' => 'onLeave']);
                $listener->addTag('kernel.event_listener', ['event' => sprintf('workflow.%s.transition', $name), 'method' => 'onTransition']);
                $listener->addTag('kernel.event_listener', ['event' => sprintf('workflow.%s.enter', $name), 'method' => 'onEnter']);
                $listener->addArgument(new Reference('logger'));
                $container->setDefinition(sprintf('.%s.listener.audit_trail', $workflowId), $listener);
            }

            // Add Guard Listener
            if ($guardsConfiguration) {
                if (!class_exists(ExpressionLanguage::class)) {
                    throw new LogicException('Cannot guard workflows as the ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
                }

                if (!class_exists(AuthenticationEvents::class)) {
                    throw new LogicException('Cannot guard workflows as the Security component is not installed. Try running "composer require symfony/security-core".');
                }

                $guard = new Definition(Workflow\EventListener\GuardListener::class);

                $guard->setArguments([
                    $guardsConfiguration,
                    new Reference('workflow.security.expression_language'),
                    new Reference('security.token_storage'),
                    new Reference('security.authorization_checker'),
                    new Reference('security.authentication.trust_resolver'),
                    new Reference('security.role_hierarchy'),
                    new Reference('validator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                ]);
                foreach ($guardsConfiguration as $eventName => $config) {
                    $guard->addTag('kernel.event_listener', ['event' => $eventName, 'method' => 'onTransition']);
                }

                $container->setDefinition(sprintf('.%s.listener.guard', $workflowId), $guard);
                $container->setParameter('workflow.has_guard_listeners', true);
            }
        }
    }

    private function registerDebugConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('debug_prod.php');

        if (class_exists(Stopwatch::class)) {
            $container->register('debug.stopwatch', Stopwatch::class)
                ->addArgument(true)
                ->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(Stopwatch::class, new Alias('debug.stopwatch', false));
        }

        $debug = $container->getParameter('kernel.debug');

        if ($debug) {
            $container->setParameter('debug.container.dump', '%kernel.build_dir%/%kernel.container_class%.xml');
        }

        if ($debug && class_exists(Stopwatch::class)) {
            $loader->load('debug.php');
        }

        $definition = $container->findDefinition('debug.error_handler_configurator');

        if (false === $config['log']) {
            $definition->replaceArgument(0, null);
        } elseif (true !== $config['log']) {
            $definition->replaceArgument(1, $config['log']);
        }

        if (!$config['throw']) {
            $container->setParameter('debug.error_handler.throw_at', 0);
        }

        if ($debug && class_exists(DebugProcessor::class)) {
            $definition = new Definition(DebugProcessor::class);
            $definition->setPublic(false);
            $definition->addArgument(new Reference('request_stack'));
            $container->setDefinition('debug.log_processor', $definition);
        }
    }

    private function registerRouterConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader, array $enabledLocales = []): void
    {
        if (!$this->readConfigEnabled('router', $container, $config)) {
            $container->removeDefinition('console.command.router_debug');
            $container->removeDefinition('console.command.router_match');
            $container->removeDefinition('messenger.middleware.router_context');

            return;
        }
        if (!class_exists(RouterContextMiddleware::class)) {
            $container->removeDefinition('messenger.middleware.router_context');
        }

        $loader->load('routing.php');

        if ($config['utf8']) {
            $container->getDefinition('routing.loader')->replaceArgument(1, ['utf8' => true]);
        }

        if ($enabledLocales) {
            $enabledLocales = implode('|', array_map('preg_quote', $enabledLocales));
            $container->getDefinition('routing.loader')->replaceArgument(2, ['_locale' => $enabledLocales]);
        }

        if (!ContainerBuilder::willBeAvailable('symfony/expression-language', ExpressionLanguage::class, ['symfony/framework-bundle', 'symfony/routing'])) {
            $container->removeDefinition('router.expression_language_provider');
        }

        $container->setParameter('router.resource', $config['resource']);
        $container->setParameter('router.cache_dir', $config['cache_dir']);
        $router = $container->findDefinition('router.default');
        $argument = $router->getArgument(2);
        $argument['strict_requirements'] = $config['strict_requirements'];
        if (isset($config['type'])) {
            $argument['resource_type'] = $config['type'];
        }
        $router->replaceArgument(2, $argument);

        $container->setParameter('request_listener.http_port', $config['http_port']);
        $container->setParameter('request_listener.https_port', $config['https_port']);

        if (null !== $config['default_uri']) {
            $container->getDefinition('router.request_context')
                ->replaceArgument(0, $config['default_uri']);
        }

        if (!class_exists(Psr4DirectoryLoader::class)) {
            $container->removeDefinition('routing.loader.psr4');
        }
    }

    private function registerSessionConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('session.php');

        // session storage
        $container->setAlias('session.storage.factory', $config['storage_factory_id']);

        $options = ['cache_limiter' => '0'];
        foreach (['name', 'cookie_lifetime', 'cookie_path', 'cookie_domain', 'cookie_secure', 'cookie_httponly', 'cookie_samesite', 'use_cookies', 'gc_maxlifetime', 'gc_probability', 'gc_divisor', 'sid_length', 'sid_bits_per_character'] as $key) {
            if (isset($config[$key])) {
                $options[$key] = $config[$key];
            }
        }

        if ('auto' === ($options['cookie_secure'] ?? null)) {
            $container->getDefinition('session.storage.factory.native')->replaceArgument(3, true);
            $container->getDefinition('session.storage.factory.php_bridge')->replaceArgument(2, true);
        }

        $container->setParameter('session.storage.options', $options);

        // session handler (the internal callback registered with PHP session management)
        if (null === $config['handler_id']) {
            // Set the handler class to be null
            $container->getDefinition('session.storage.factory.native')->replaceArgument(1, null);
            $container->getDefinition('session.storage.factory.php_bridge')->replaceArgument(0, null);

            $container->setAlias('session.handler', 'session.handler.native_file');
        } else {
            $container->resolveEnvPlaceholders($config['handler_id'], null, $usedEnvs);

            if ($usedEnvs || preg_match('#^[a-z]++://#', $config['handler_id'])) {
                $id = '.cache_connection.'.ContainerBuilder::hash($config['handler_id']);

                $container->getDefinition('session.abstract_handler')
                    ->replaceArgument(0, $container->hasDefinition($id) ? new Reference($id) : $config['handler_id']);

                $container->setAlias('session.handler', 'session.abstract_handler');
            } else {
                $container->setAlias('session.handler', $config['handler_id']);
            }
        }

        $container->setParameter('session.save_path', $config['save_path']);

        $container->setParameter('session.metadata.update_threshold', $config['metadata_update_threshold']);
    }

    private function registerRequestConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if ($config['formats']) {
            $loader->load('request.php');

            $listener = $container->getDefinition('request.add_request_formats_listener');
            $listener->replaceArgument(0, $config['formats']);
        }
    }

    private function registerAssetsConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('assets.php');

        if ($config['version_strategy']) {
            $defaultVersion = new Reference($config['version_strategy']);
        } else {
            $defaultVersion = $this->createVersion($container, $config['version'], $config['version_format'], $config['json_manifest_path'], '_default', $config['strict_mode']);
        }

        $defaultPackage = $this->createPackageDefinition($config['base_path'], $config['base_urls'], $defaultVersion);
        $container->setDefinition('assets._default_package', $defaultPackage);

        foreach ($config['packages'] as $name => $package) {
            if (null !== $package['version_strategy']) {
                $version = new Reference($package['version_strategy']);
            } elseif (!\array_key_exists('version', $package) && null === $package['json_manifest_path']) {
                // if neither version nor json_manifest_path are specified, use the default
                $version = $defaultVersion;
            } else {
                // let format fallback to main version_format
                $format = $package['version_format'] ?: $config['version_format'];
                $version = $package['version'] ?? null;
                $version = $this->createVersion($container, $version, $format, $package['json_manifest_path'], $name, $package['strict_mode']);
            }

            $packageDefinition = $this->createPackageDefinition($package['base_path'], $package['base_urls'], $version)
                ->addTag('assets.package', ['package' => $name]);
            $container->setDefinition('assets._package_'.$name, $packageDefinition);
            $container->registerAliasForArgument('assets._package_'.$name, PackageInterface::class, $name.'.package');
        }
    }

    /**
     * Returns a definition for an asset package.
     */
    private function createPackageDefinition(?string $basePath, array $baseUrls, Reference $version): Definition
    {
        if ($basePath && $baseUrls) {
            throw new \LogicException('An asset package cannot have base URLs and base paths.');
        }

        $package = new ChildDefinition($baseUrls ? 'assets.url_package' : 'assets.path_package');
        $package
            ->setPublic(false)
            ->replaceArgument(0, $baseUrls ?: $basePath)
            ->replaceArgument(1, $version)
        ;

        return $package;
    }

    private function createVersion(ContainerBuilder $container, ?string $version, ?string $format, ?string $jsonManifestPath, string $name, bool $strictMode): Reference
    {
        // Configuration prevents $version and $jsonManifestPath from being set
        if (null !== $version) {
            $def = new ChildDefinition('assets.static_version_strategy');
            $def
                ->replaceArgument(0, $version)
                ->replaceArgument(1, $format)
            ;
            $container->setDefinition('assets._version_'.$name, $def);

            return new Reference('assets._version_'.$name);
        }

        if (null !== $jsonManifestPath) {
            $def = new ChildDefinition('assets.json_manifest_version_strategy');
            $def->replaceArgument(0, $jsonManifestPath);
            $def->replaceArgument(2, $strictMode);
            $container->setDefinition('assets._version_'.$name, $def);

            return new Reference('assets._version_'.$name);
        }

        return new Reference('assets.empty_version_strategy');
    }

    private function registerTranslatorConfiguration(array $config, ContainerBuilder $container, LoaderInterface $loader, string $defaultLocale, array $enabledLocales): void
    {
        if (!$this->readConfigEnabled('translator', $container, $config)) {
            $container->removeDefinition('console.command.translation_debug');
            $container->removeDefinition('console.command.translation_extract');
            $container->removeDefinition('console.command.translation_pull');
            $container->removeDefinition('console.command.translation_push');

            return;
        }

        $loader->load('translation.php');

        if (!ContainerBuilder::willBeAvailable('symfony/translation', LocaleSwitcher::class, ['symfony/framework-bundle'])) {
            $container->removeDefinition('translation.locale_switcher');
        }

        // don't use ContainerBuilder::willBeAvailable() as these are not needed in production
        if (interface_exists(Parser::class) && class_exists(PhpAstExtractor::class)) {
            $container->removeDefinition('translation.extractor.php');
        } else {
            $container->removeDefinition('translation.extractor.php_ast');
        }

        $loader->load('translation_providers.php');

        // Use the "real" translator instead of the identity default
        $container->setAlias('translator', 'translator.default')->setPublic(true);
        $container->setAlias('translator.formatter', new Alias($config['formatter'], false));
        $translator = $container->findDefinition('translator.default');
        $translator->addMethodCall('setFallbackLocales', [$config['fallbacks'] ?: [$defaultLocale]]);

        $defaultOptions = $translator->getArgument(4);
        $defaultOptions['cache_dir'] = $config['cache_dir'];
        $translator->setArgument(4, $defaultOptions);
        $translator->setArgument(5, $enabledLocales);

        $container->setParameter('translator.logging', $config['logging']);
        $container->setParameter('translator.default_path', $config['default_path']);

        // Discover translation directories
        $dirs = [];
        $transPaths = [];
        $nonExistingDirs = [];
        if (ContainerBuilder::willBeAvailable('symfony/validator', Validation::class, ['symfony/framework-bundle', 'symfony/translation'])) {
            $r = new \ReflectionClass(Validation::class);

            $dirs[] = $transPaths[] = \dirname($r->getFileName()).'/Resources/translations';
        }
        if (ContainerBuilder::willBeAvailable('symfony/form', Form::class, ['symfony/framework-bundle', 'symfony/translation'])) {
            $r = new \ReflectionClass(Form::class);

            $dirs[] = $transPaths[] = \dirname($r->getFileName()).'/Resources/translations';
        }
        if (ContainerBuilder::willBeAvailable('symfony/security-core', AuthenticationException::class, ['symfony/framework-bundle', 'symfony/translation'])) {
            $r = new \ReflectionClass(AuthenticationException::class);

            $dirs[] = $transPaths[] = \dirname($r->getFileName(), 2).'/Resources/translations';
        }
        $defaultDir = $container->getParameterBag()->resolveValue($config['default_path']);
        foreach ($container->getParameter('kernel.bundles_metadata') as $name => $bundle) {
            if ($container->fileExists($dir = $bundle['path'].'/Resources/translations') || $container->fileExists($dir = $bundle['path'].'/translations')) {
                $dirs[] = $dir;
            } else {
                $nonExistingDirs[] = $dir;
            }
        }

        foreach ($config['paths'] as $dir) {
            if ($container->fileExists($dir)) {
                $dirs[] = $transPaths[] = $dir;
            } else {
                throw new \UnexpectedValueException(sprintf('"%s" defined in translator.paths does not exist or is not a directory.', $dir));
            }
        }

        if ($container->hasDefinition('console.command.translation_debug')) {
            $container->getDefinition('console.command.translation_debug')->replaceArgument(5, $transPaths);
        }

        if ($container->hasDefinition('console.command.translation_extract')) {
            $container->getDefinition('console.command.translation_extract')->replaceArgument(6, $transPaths);
        }

        if (null === $defaultDir) {
            // allow null
        } elseif ($container->fileExists($defaultDir)) {
            $dirs[] = $defaultDir;
        } else {
            $nonExistingDirs[] = $defaultDir;
        }

        // Register translation resources
        if ($dirs) {
            $files = [];

            foreach ($dirs as $dir) {
                $finder = Finder::create()
                    ->followLinks()
                    ->files()
                    ->filter(fn (\SplFileInfo $file) => 2 <= substr_count($file->getBasename(), '.') && preg_match('/\.\w+$/', $file->getBasename()))
                    ->in($dir)
                    ->sortByName()
                ;
                foreach ($finder as $file) {
                    $fileNameParts = explode('.', basename($file));
                    $locale = $fileNameParts[\count($fileNameParts) - 2];
                    if (!isset($files[$locale])) {
                        $files[$locale] = [];
                    }

                    $files[$locale][] = (string) $file;
                }
            }

            $projectDir = $container->getParameter('kernel.project_dir');

            $options = array_merge(
                $translator->getArgument(4),
                [
                    'resource_files' => $files,
                    'scanned_directories' => $scannedDirectories = array_merge($dirs, $nonExistingDirs),
                    'cache_vary' => [
                        'scanned_directories' => array_map(static fn (string $dir): string => str_starts_with($dir, $projectDir.'/') ? substr($dir, 1 + \strlen($projectDir)) : $dir, $scannedDirectories),
                    ],
                ]
            );

            $translator->replaceArgument(4, $options);
        }

        if ($config['pseudo_localization']['enabled']) {
            $options = $config['pseudo_localization'];
            unset($options['enabled']);

            $container
                ->register('translator.pseudo', PseudoLocalizationTranslator::class)
                ->setDecoratedService('translator', null, -1) // Lower priority than "translator.data_collector"
                ->setArguments([
                    new Reference('translator.pseudo.inner'),
                    $options,
                ]);
        }

        $classToServices = [
            CrowdinProviderFactory::class => 'translation.provider_factory.crowdin',
            LocoProviderFactory::class => 'translation.provider_factory.loco',
            LokaliseProviderFactory::class => 'translation.provider_factory.lokalise',
        ];

        $parentPackages = ['symfony/framework-bundle', 'symfony/translation', 'symfony/http-client'];

        foreach ($classToServices as $class => $service) {
            $package = substr($service, \strlen('translation.provider_factory.'));

            if (!$container->hasDefinition('http_client') || !ContainerBuilder::willBeAvailable(sprintf('symfony/%s-translation-provider', $package), $class, $parentPackages)) {
                $container->removeDefinition($service);
            }
        }

        if (!$config['providers']) {
            return;
        }

        $locales = $enabledLocales;

        foreach ($config['providers'] as $provider) {
            if ($provider['locales']) {
                $locales += $provider['locales'];
            }
        }

        $locales = array_unique($locales);

        $container->getDefinition('console.command.translation_pull')
            ->replaceArgument(4, array_merge($transPaths, [$config['default_path']]))
            ->replaceArgument(5, $locales)
        ;

        $container->getDefinition('console.command.translation_push')
            ->replaceArgument(2, array_merge($transPaths, [$config['default_path']]))
            ->replaceArgument(3, $locales)
        ;

        $container->getDefinition('translation.provider_collection_factory')
            ->replaceArgument(1, $locales)
        ;

        $container->getDefinition('translation.provider_collection')->setArgument(0, $config['providers']);
    }

    private function registerValidationConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader, bool $propertyInfoEnabled): void
    {
        if (!$this->readConfigEnabled('validation', $container, $config)) {
            $container->removeDefinition('console.command.validator_debug');

            return;
        }

        if (!class_exists(Validation::class)) {
            throw new LogicException('Validation support cannot be enabled as the Validator component is not installed. Try running "composer require symfony/validator".');
        }

        if (!isset($config['email_validation_mode'])) {
            $config['email_validation_mode'] = 'loose';
        }

        $loader->load('validator.php');

        $validatorBuilder = $container->getDefinition('validator.builder');

        $container->setParameter('validator.translation_domain', $config['translation_domain']);

        $files = ['xml' => [], 'yml' => []];
        $this->registerValidatorMapping($container, $config, $files);

        if (!empty($files['xml'])) {
            $validatorBuilder->addMethodCall('addXmlMappings', [$files['xml']]);
        }

        if (!empty($files['yml'])) {
            $validatorBuilder->addMethodCall('addYamlMappings', [$files['yml']]);
        }

        $definition = $container->findDefinition('validator.email');
        $definition->replaceArgument(0, $config['email_validation_mode']);

        if (\array_key_exists('enable_annotations', $config) && $config['enable_annotations']) {
            $validatorBuilder->addMethodCall('enableAnnotationMapping', [true]);
            if ($this->isInitializedConfigEnabled('annotations')) {
                $validatorBuilder->addMethodCall('setDoctrineAnnotationReader', [new Reference('annotation_reader')]);
            }
        }

        if (\array_key_exists('static_method', $config) && $config['static_method']) {
            foreach ($config['static_method'] as $methodName) {
                $validatorBuilder->addMethodCall('addMethodMapping', [$methodName]);
            }
        }

        if (!$container->getParameter('kernel.debug')) {
            $validatorBuilder->addMethodCall('setMappingCache', [new Reference('validator.mapping.cache.adapter')]);
        }

        $container->setParameter('validator.auto_mapping', $config['auto_mapping']);
        if (!$propertyInfoEnabled || !class_exists(PropertyInfoLoader::class)) {
            $container->removeDefinition('validator.property_info_loader');
        }

        $container
            ->getDefinition('validator.not_compromised_password')
            ->setArgument(2, $config['not_compromised_password']['enabled'])
            ->setArgument(3, $config['not_compromised_password']['endpoint'])
        ;

        if (!class_exists(ExpressionLanguage::class)) {
            $container->removeDefinition('validator.expression_language');
        }

        if (!class_exists(WhenValidator::class)) {
            $container->removeDefinition('validator.when');
        }
    }

    private function registerValidatorMapping(ContainerBuilder $container, array $config, array &$files): void
    {
        $fileRecorder = function ($extension, $path) use (&$files) {
            $files['yaml' === $extension ? 'yml' : $extension][] = $path;
        };

        if (ContainerBuilder::willBeAvailable('symfony/form', Form::class, ['symfony/framework-bundle', 'symfony/validator'])) {
            $reflClass = new \ReflectionClass(Form::class);
            $fileRecorder('xml', \dirname($reflClass->getFileName()).'/Resources/config/validation.xml');
        }

        foreach ($container->getParameter('kernel.bundles_metadata') as $bundle) {
            $configDir = is_dir($bundle['path'].'/Resources/config') ? $bundle['path'].'/Resources/config' : $bundle['path'].'/config';

            if (
                $container->fileExists($file = $configDir.'/validation.yaml', false) ||
                $container->fileExists($file = $configDir.'/validation.yml', false)
            ) {
                $fileRecorder('yml', $file);
            }

            if ($container->fileExists($file = $configDir.'/validation.xml', false)) {
                $fileRecorder('xml', $file);
            }

            if ($container->fileExists($dir = $configDir.'/validation', '/^$/')) {
                $this->registerMappingFilesFromDir($dir, $fileRecorder);
            }
        }

        $projectDir = $container->getParameter('kernel.project_dir');
        if ($container->fileExists($dir = $projectDir.'/config/validator', '/^$/')) {
            $this->registerMappingFilesFromDir($dir, $fileRecorder);
        }

        $this->registerMappingFilesFromConfig($container, $config, $fileRecorder);
    }

    private function registerMappingFilesFromDir(string $dir, callable $fileRecorder): void
    {
        foreach (Finder::create()->followLinks()->files()->in($dir)->name('/\.(xml|ya?ml)$/')->sortByName() as $file) {
            $fileRecorder($file->getExtension(), $file->getRealPath());
        }
    }

    private function registerMappingFilesFromConfig(ContainerBuilder $container, array $config, callable $fileRecorder): void
    {
        foreach ($config['mapping']['paths'] as $path) {
            if (is_dir($path)) {
                $this->registerMappingFilesFromDir($path, $fileRecorder);
                $container->addResource(new DirectoryResource($path, '/^$/'));
            } elseif ($container->fileExists($path, false)) {
                if (!preg_match('/\.(xml|ya?ml)$/', $path, $matches)) {
                    throw new \RuntimeException(sprintf('Unsupported mapping type in "%s", supported types are XML & Yaml.', $path));
                }
                $fileRecorder($matches[1], $path);
            } else {
                throw new \RuntimeException(sprintf('Could not open file or directory "%s".', $path));
            }
        }
    }

    private function registerAnnotationsConfiguration(array $config, ContainerBuilder $container, LoaderInterface $loader): void
    {
        if (!$this->isInitializedConfigEnabled('annotations')) {
            return;
        }

        if (!class_exists(\Doctrine\Common\Annotations\Annotation::class)) {
            throw new LogicException('Annotations cannot be enabled as the Doctrine Annotation library is not installed. Try running "composer require doctrine/annotations".');
        }

        $loader->load('annotations.php');

        if ('none' === $config['cache']) {
            $container->removeDefinition('annotations.cached_reader');

            return;
        }

        if ('php_array' === $config['cache']) {
            $cacheService = 'annotations.cache_adapter';

            // Enable warmer only if PHP array is used for cache
            $definition = $container->findDefinition('annotations.cache_warmer');
            $definition->addTag('kernel.cache_warmer');
        } else {
            $cacheService = 'annotations.filesystem_cache_adapter';
            $cacheDir = $container->getParameterBag()->resolveValue($config['file_cache_dir']);

            if (!is_dir($cacheDir) && false === @mkdir($cacheDir, 0777, true) && !is_dir($cacheDir)) {
                throw new \RuntimeException(sprintf('Could not create cache directory "%s".', $cacheDir));
            }

            $container
                ->getDefinition('annotations.filesystem_cache_adapter')
                ->replaceArgument(2, $cacheDir)
            ;
        }

        $container
            ->getDefinition('annotations.cached_reader')
            ->replaceArgument(2, $config['debug'])
            // reference the cache provider without using it until AddAnnotationsCachedReaderPass runs
            ->addArgument(new ServiceClosureArgument(new Reference($cacheService)))
        ;

        $container->setAlias('annotation_reader', 'annotations.cached_reader');
        $container->setAlias(Reader::class, new Alias('annotations.cached_reader', false));
        $container->removeDefinition('annotations.psr_cached_reader');
    }

    private function registerPropertyAccessConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!$this->readConfigEnabled('property_access', $container, $config)) {
            return;
        }

        $loader->load('property_access.php');

        $magicMethods = PropertyAccessor::DISALLOW_MAGIC_METHODS;
        $magicMethods |= $config['magic_call'] ? PropertyAccessor::MAGIC_CALL : 0;
        $magicMethods |= $config['magic_get'] ? PropertyAccessor::MAGIC_GET : 0;
        $magicMethods |= $config['magic_set'] ? PropertyAccessor::MAGIC_SET : 0;

        $throw = PropertyAccessor::DO_NOT_THROW;
        $throw |= $config['throw_exception_on_invalid_index'] ? PropertyAccessor::THROW_ON_INVALID_INDEX : 0;
        $throw |= $config['throw_exception_on_invalid_property_path'] ? PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH : 0;

        $container
            ->getDefinition('property_accessor')
            ->replaceArgument(0, $magicMethods)
            ->replaceArgument(1, $throw)
            ->replaceArgument(3, new Reference(PropertyReadInfoExtractorInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ->replaceArgument(4, new Reference(PropertyWriteInfoExtractorInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
        ;
    }

    private function registerSecretsConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!$this->readConfigEnabled('secrets', $container, $config)) {
            $container->removeDefinition('console.command.secrets_set');
            $container->removeDefinition('console.command.secrets_list');
            $container->removeDefinition('console.command.secrets_remove');
            $container->removeDefinition('console.command.secrets_generate_key');
            $container->removeDefinition('console.command.secrets_decrypt_to_local');
            $container->removeDefinition('console.command.secrets_encrypt_from_local');

            return;
        }

        $loader->load('secrets.php');

        $container->getDefinition('secrets.vault')->replaceArgument(0, $config['vault_directory']);

        if ($config['local_dotenv_file']) {
            $container->getDefinition('secrets.local_vault')->replaceArgument(0, $config['local_dotenv_file']);
        } else {
            $container->removeDefinition('secrets.local_vault');
        }

        if ($config['decryption_env_var']) {
            if (!preg_match('/^(?:[-.\w\\\\]*+:)*+\w++$/', $config['decryption_env_var'])) {
                throw new InvalidArgumentException(sprintf('Invalid value "%s" set as "decryption_env_var": only "word" characters are allowed.', $config['decryption_env_var']));
            }

            if (ContainerBuilder::willBeAvailable('symfony/string', LazyString::class, ['symfony/framework-bundle'])) {
                $container->getDefinition('secrets.decryption_key')->replaceArgument(1, $config['decryption_env_var']);
            } else {
                $container->getDefinition('secrets.vault')->replaceArgument(1, "%env({$config['decryption_env_var']})%");
                $container->removeDefinition('secrets.decryption_key');
            }
        } else {
            $container->getDefinition('secrets.vault')->replaceArgument(1, null);
            $container->removeDefinition('secrets.decryption_key');
        }
    }

    private function registerSecurityCsrfConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!$this->readConfigEnabled('csrf_protection', $container, $config)) {
            return;
        }

        if (!class_exists(\Symfony\Component\Security\Csrf\CsrfToken::class)) {
            throw new LogicException('CSRF support cannot be enabled as the Security CSRF component is not installed. Try running "composer require symfony/security-csrf".');
        }

        if (!$this->isInitializedConfigEnabled('session')) {
            throw new \LogicException('CSRF protection needs sessions to be enabled.');
        }

        // Enable services for CSRF protection (even without forms)
        $loader->load('security_csrf.php');

        if (!class_exists(CsrfExtension::class)) {
            $container->removeDefinition('twig.extension.security_csrf');
        }
    }

    private function registerSerializerConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('serializer.php');
        if ($container->getParameter('kernel.debug')) {
            $container->removeDefinition('serializer.mapping.cache_class_metadata_factory');
        }

        $chainLoader = $container->getDefinition('serializer.mapping.chain_loader');

        if (!$this->isInitializedConfigEnabled('property_access')) {
            $container->removeAlias('serializer.property_accessor');
            $container->removeDefinition('serializer.normalizer.object');
        }

        if (!class_exists(Yaml::class)) {
            $container->removeDefinition('serializer.encoder.yaml');
        }

        if (!class_exists(UnwrappingDenormalizer::class) || !$this->isInitializedConfigEnabled('property_access')) {
            $container->removeDefinition('serializer.denormalizer.unwrapping');
        }

        if (!class_exists(Headers::class)) {
            $container->removeDefinition('serializer.normalizer.mime_message');
        }

        $serializerLoaders = [];
        if (isset($config['enable_annotations']) && $config['enable_annotations']) {
            $annotationLoader = new Definition(
                AnnotationLoader::class,
                [new Reference('annotation_reader', ContainerInterface::NULL_ON_INVALID_REFERENCE)]
            );
            $annotationLoader->setPublic(false);

            $serializerLoaders[] = $annotationLoader;
        }

        $fileRecorder = function ($extension, $path) use (&$serializerLoaders) {
            $definition = new Definition(\in_array($extension, ['yaml', 'yml']) ? YamlFileLoader::class : XmlFileLoader::class, [$path]);
            $definition->setPublic(false);
            $serializerLoaders[] = $definition;
        };

        foreach ($container->getParameter('kernel.bundles_metadata') as $bundle) {
            $configDir = is_dir($bundle['path'].'/Resources/config') ? $bundle['path'].'/Resources/config' : $bundle['path'].'/config';

            if ($container->fileExists($file = $configDir.'/serialization.xml', false)) {
                $fileRecorder('xml', $file);
            }

            if (
                $container->fileExists($file = $configDir.'/serialization.yaml', false) ||
                $container->fileExists($file = $configDir.'/serialization.yml', false)
            ) {
                $fileRecorder('yml', $file);
            }

            if ($container->fileExists($dir = $configDir.'/serialization', '/^$/')) {
                $this->registerMappingFilesFromDir($dir, $fileRecorder);
            }
        }

        $projectDir = $container->getParameter('kernel.project_dir');
        if ($container->fileExists($dir = $projectDir.'/config/serializer', '/^$/')) {
            $this->registerMappingFilesFromDir($dir, $fileRecorder);
        }

        $this->registerMappingFilesFromConfig($container, $config, $fileRecorder);

        $chainLoader->replaceArgument(0, $serializerLoaders);
        $container->getDefinition('serializer.mapping.cache_warmer')->replaceArgument(0, $serializerLoaders);

        if (isset($config['name_converter']) && $config['name_converter']) {
            $container->getDefinition('serializer.name_converter.metadata_aware')->setArgument(1, new Reference($config['name_converter']));
        }

        if (isset($config['circular_reference_handler']) && $config['circular_reference_handler']) {
            $arguments = $container->getDefinition('serializer.normalizer.object')->getArguments();
            $context = ($arguments[6] ?? []) + ['circular_reference_handler' => new Reference($config['circular_reference_handler'])];
            $container->getDefinition('serializer.normalizer.object')->setArgument(5, null);
            $container->getDefinition('serializer.normalizer.object')->setArgument(6, $context);
        }

        if ($config['max_depth_handler'] ?? false) {
            $defaultContext = $container->getDefinition('serializer.normalizer.object')->getArgument(6);
            $defaultContext += ['max_depth_handler' => new Reference($config['max_depth_handler'])];
            $container->getDefinition('serializer.normalizer.object')->replaceArgument(6, $defaultContext);
        }

        if (isset($config['default_context']) && $config['default_context']) {
            $container->setParameter('serializer.default_context', $config['default_context']);
        }
    }

    private function registerPropertyInfoConfiguration(ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!interface_exists(PropertyInfoExtractorInterface::class)) {
            throw new LogicException('PropertyInfo support cannot be enabled as the PropertyInfo component is not installed. Try running "composer require symfony/property-info".');
        }

        $loader->load('property_info.php');

        if (
            ContainerBuilder::willBeAvailable('phpstan/phpdoc-parser', PhpDocParser::class, ['symfony/framework-bundle', 'symfony/property-info'])
            && ContainerBuilder::willBeAvailable('phpdocumentor/type-resolver', ContextFactory::class, ['symfony/framework-bundle', 'symfony/property-info'])
        ) {
            $definition = $container->register('property_info.phpstan_extractor', PhpStanExtractor::class);
            $definition->addTag('property_info.type_extractor', ['priority' => -1000]);
        }

        if (ContainerBuilder::willBeAvailable('phpdocumentor/reflection-docblock', DocBlockFactoryInterface::class, ['symfony/framework-bundle', 'symfony/property-info'], true)) {
            $definition = $container->register('property_info.php_doc_extractor', PhpDocExtractor::class);
            $definition->addTag('property_info.description_extractor', ['priority' => -1000]);
            $definition->addTag('property_info.type_extractor', ['priority' => -1001]);
        }

        if ($container->getParameter('kernel.debug')) {
            $container->removeDefinition('property_info.cache');
        }
    }

    private function registerLockConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('lock.php');

        foreach ($config['resources'] as $resourceName => $resourceStores) {
            if (0 === \count($resourceStores)) {
                continue;
            }

            // Generate stores
            $storeDefinitions = [];
            foreach ($resourceStores as $resourceStore) {
                $storeDsn = $container->resolveEnvPlaceholders($resourceStore, null, $usedEnvs);
                $storeDefinition = new Definition(PersistingStoreInterface::class);
                $storeDefinition
                    ->setFactory([StoreFactory::class, 'createStore'])
                    ->setArguments([$resourceStore])
                    ->addTag('lock.store');

                $container->setDefinition($storeDefinitionId = '.lock.'.$resourceName.'.store.'.$container->hash($storeDsn), $storeDefinition);

                $storeDefinition = new Reference($storeDefinitionId);

                $storeDefinitions[] = $storeDefinition;
            }

            // Wrap array of stores with CombinedStore
            if (\count($storeDefinitions) > 1) {
                $combinedDefinition = new ChildDefinition('lock.store.combined.abstract');
                $combinedDefinition->replaceArgument(0, $storeDefinitions);
                $container->setDefinition($storeDefinitionId = '.lock.'.$resourceName.'.store.'.$container->hash($resourceStores), $combinedDefinition);
            }

            // Generate factories for each resource
            $factoryDefinition = new ChildDefinition('lock.factory.abstract');
            $factoryDefinition->replaceArgument(0, new Reference($storeDefinitionId));
            $container->setDefinition('lock.'.$resourceName.'.factory', $factoryDefinition);

            // provide alias for default resource
            if ('default' === $resourceName) {
                $container->setAlias('lock.factory', new Alias('lock.'.$resourceName.'.factory', false));
                $container->setAlias(LockFactory::class, new Alias('lock.factory', false));
            } else {
                $container->registerAliasForArgument('lock.'.$resourceName.'.factory', LockFactory::class, $resourceName.'.lock.factory');
            }
        }
    }

    private function registerSemaphoreConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('semaphore.php');

        foreach ($config['resources'] as $resourceName => $resourceStore) {
            $storeDsn = $container->resolveEnvPlaceholders($resourceStore, null, $usedEnvs);
            $storeDefinition = new Definition(SemaphoreStoreInterface::class);
            $storeDefinition->setFactory([SemaphoreStoreFactory::class, 'createStore']);
            $storeDefinition->setArguments([$resourceStore]);

            $container->setDefinition($storeDefinitionId = '.semaphore.'.$resourceName.'.store.'.$container->hash($storeDsn), $storeDefinition);

            // Generate factories for each resource
            $factoryDefinition = new ChildDefinition('semaphore.factory.abstract');
            $factoryDefinition->replaceArgument(0, new Reference($storeDefinitionId));
            $container->setDefinition('semaphore.'.$resourceName.'.factory', $factoryDefinition);

            // Generate services for semaphore instances
            $semaphoreDefinition = new Definition(Semaphore::class);
            $semaphoreDefinition->setPublic(false);
            $semaphoreDefinition->setFactory([new Reference('semaphore.'.$resourceName.'.factory'), 'createSemaphore']);
            $semaphoreDefinition->setArguments([$resourceName]);

            // provide alias for default resource
            if ('default' === $resourceName) {
                $container->setAlias('semaphore.factory', new Alias('semaphore.'.$resourceName.'.factory', false));
                $container->setAlias(SemaphoreFactory::class, new Alias('semaphore.factory', false));
            } else {
                $container->registerAliasForArgument('semaphore.'.$resourceName.'.factory', SemaphoreFactory::class, $resourceName.'.semaphore.factory');
            }
        }
    }

    private function registerMessengerConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader, array $validationConfig): void
    {
        if (!interface_exists(MessageBusInterface::class)) {
            throw new LogicException('Messenger support cannot be enabled as the Messenger component is not installed. Try running "composer require symfony/messenger".');
        }

        if (!$this->hasConsole() || !class_exists(StatsCommand::class)) {
            $container->removeDefinition('console.command.messenger_stats');
        }

        $loader->load('messenger.php');

        if (!interface_exists(DenormalizerInterface::class)) {
            $container->removeDefinition('serializer.normalizer.flatten_exception');
        }

        if (ContainerBuilder::willBeAvailable('symfony/amqp-messenger', AmqpTransportFactory::class, ['symfony/framework-bundle', 'symfony/messenger'])) {
            $container->getDefinition('messenger.transport.amqp.factory')->addTag('messenger.transport_factory');
        }

        if (ContainerBuilder::willBeAvailable('symfony/redis-messenger', RedisTransportFactory::class, ['symfony/framework-bundle', 'symfony/messenger'])) {
            $container->getDefinition('messenger.transport.redis.factory')->addTag('messenger.transport_factory');
        }

        if (ContainerBuilder::willBeAvailable('symfony/amazon-sqs-messenger', AmazonSqsTransportFactory::class, ['symfony/framework-bundle', 'symfony/messenger'])) {
            $container->getDefinition('messenger.transport.sqs.factory')->addTag('messenger.transport_factory');
        }

        if (ContainerBuilder::willBeAvailable('symfony/beanstalkd-messenger', BeanstalkdTransportFactory::class, ['symfony/framework-bundle', 'symfony/messenger'])) {
            $container->getDefinition('messenger.transport.beanstalkd.factory')->addTag('messenger.transport_factory');
        }

        if (null === $config['default_bus'] && 1 === \count($config['buses'])) {
            $config['default_bus'] = key($config['buses']);
        }

        $defaultMiddleware = [
            'before' => [
                ['id' => 'add_bus_name_stamp_middleware'],
                ['id' => 'reject_redelivered_message_middleware'],
                ['id' => 'dispatch_after_current_bus'],
                ['id' => 'failed_message_processing_middleware'],
            ],
            'after' => [
                ['id' => 'send_message'],
                ['id' => 'handle_message'],
            ],
        ];
        foreach ($config['buses'] as $busId => $bus) {
            $middleware = $bus['middleware'];

            if ($bus['default_middleware']['enabled']) {
                $defaultMiddleware['after'][0]['arguments'] = [$bus['default_middleware']['allow_no_senders']];
                $defaultMiddleware['after'][1]['arguments'] = [$bus['default_middleware']['allow_no_handlers']];

                // argument to add_bus_name_stamp_middleware
                $defaultMiddleware['before'][0]['arguments'] = [$busId];

                $middleware = array_merge($defaultMiddleware['before'], $middleware, $defaultMiddleware['after']);
            }

            foreach ($middleware as $middlewareItem) {
                if (!$validationConfig['enabled'] && \in_array($middlewareItem['id'], ['validation', 'messenger.middleware.validation'], true)) {
                    throw new LogicException('The Validation middleware is only available when the Validator component is installed and enabled. Try running "composer require symfony/validator".');
                }
            }

            if ($container->getParameter('kernel.debug') && class_exists(Stopwatch::class)) {
                array_unshift($middleware, ['id' => 'traceable', 'arguments' => [$busId]]);
            }

            $container->setParameter($busId.'.middleware', $middleware);
            $container->register($busId, MessageBus::class)->addArgument([])->addTag('messenger.bus');

            if ($busId === $config['default_bus']) {
                $container->setAlias('messenger.default_bus', $busId)->setPublic(true);
                $container->setAlias(MessageBusInterface::class, $busId);
            } else {
                $container->registerAliasForArgument($busId, MessageBusInterface::class);
            }
        }

        if (empty($config['transports'])) {
            $container->removeDefinition('messenger.transport.symfony_serializer');
            $container->removeDefinition('messenger.transport.amqp.factory');
            $container->removeDefinition('messenger.transport.redis.factory');
            $container->removeDefinition('messenger.transport.sqs.factory');
            $container->removeDefinition('messenger.transport.beanstalkd.factory');
            $container->removeAlias(SerializerInterface::class);
        } else {
            $container->getDefinition('messenger.transport.symfony_serializer')
                ->replaceArgument(1, $config['serializer']['symfony_serializer']['format'])
                ->replaceArgument(2, $config['serializer']['symfony_serializer']['context']);
            $container->setAlias('messenger.default_serializer', $config['serializer']['default_serializer']);
        }

        $failureTransports = [];
        if ($config['failure_transport']) {
            if (!isset($config['transports'][$config['failure_transport']])) {
                throw new LogicException(sprintf('Invalid Messenger configuration: the failure transport "%s" is not a valid transport or service id.', $config['failure_transport']));
            }

            $container->setAlias('messenger.failure_transports.default', 'messenger.transport.'.$config['failure_transport']);
            $failureTransports[] = $config['failure_transport'];
        }

        $failureTransportsByName = [];
        foreach ($config['transports'] as $name => $transport) {
            if ($transport['failure_transport']) {
                $failureTransports[] = $transport['failure_transport'];
                $failureTransportsByName[$name] = $transport['failure_transport'];
            } elseif ($config['failure_transport']) {
                $failureTransportsByName[$name] = $config['failure_transport'];
            }
        }

        $senderAliases = [];
        $transportRetryReferences = [];
        $transportRateLimiterReferences = [];
        foreach ($config['transports'] as $name => $transport) {
            $serializerId = $transport['serializer'] ?? 'messenger.default_serializer';
            $transportDefinition = (new Definition(TransportInterface::class))
                ->setFactory([new Reference('messenger.transport_factory'), 'createTransport'])
                ->setArguments([$transport['dsn'], $transport['options'] + ['transport_name' => $name], new Reference($serializerId)])
                ->addTag('messenger.receiver', [
                        'alias' => $name,
                        'is_failure_transport' => \in_array($name, $failureTransports),
                    ]
                )
            ;
            $container->setDefinition($transportId = 'messenger.transport.'.$name, $transportDefinition);
            $senderAliases[$name] = $transportId;

            if (null !== $transport['retry_strategy']['service']) {
                $transportRetryReferences[$name] = new Reference($transport['retry_strategy']['service']);
            } else {
                $retryServiceId = sprintf('messenger.retry.multiplier_retry_strategy.%s', $name);
                $retryDefinition = new ChildDefinition('messenger.retry.abstract_multiplier_retry_strategy');
                $retryDefinition
                    ->replaceArgument(0, $transport['retry_strategy']['max_retries'])
                    ->replaceArgument(1, $transport['retry_strategy']['delay'])
                    ->replaceArgument(2, $transport['retry_strategy']['multiplier'])
                    ->replaceArgument(3, $transport['retry_strategy']['max_delay']);
                $container->setDefinition($retryServiceId, $retryDefinition);

                $transportRetryReferences[$name] = new Reference($retryServiceId);
            }

            if ($transport['rate_limiter']) {
                if (!interface_exists(LimiterInterface::class)) {
                    throw new LogicException('Rate limiter cannot be used within Messenger as the RateLimiter component is not installed. Try running "composer require symfony/rate-limiter".');
                }

                $transportRateLimiterReferences[$name] = new Reference('limiter.'.$transport['rate_limiter']);
            }
        }

        $senderReferences = [];
        // alias => service_id
        foreach ($senderAliases as $alias => $serviceId) {
            $senderReferences[$alias] = new Reference($serviceId);
        }
        // service_id => service_id
        foreach ($senderAliases as $serviceId) {
            $senderReferences[$serviceId] = new Reference($serviceId);
        }

        foreach ($config['transports'] as $name => $transport) {
            if ($transport['failure_transport']) {
                if (!isset($senderReferences[$transport['failure_transport']])) {
                    throw new LogicException(sprintf('Invalid Messenger configuration: the failure transport "%s" is not a valid transport or service id.', $transport['failure_transport']));
                }
            }
        }

        $failureTransportReferencesByTransportName = array_map(fn ($failureTransportName) => $senderReferences[$failureTransportName], $failureTransportsByName);

        $messageToSendersMapping = [];
        foreach ($config['routing'] as $message => $messageConfiguration) {
            if ('*' !== $message && !class_exists($message) && !interface_exists($message, false) && !preg_match('/^(?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+\\\\)++\*$/', $message)) {
                if (str_contains($message, '*')) {
                    throw new LogicException(sprintf('Invalid Messenger routing configuration: invalid namespace "%s" wildcard.', $message));
                }

                throw new LogicException(sprintf('Invalid Messenger routing configuration: class or interface "%s" not found.', $message));
            }

            // make sure senderAliases contains all senders
            foreach ($messageConfiguration['senders'] as $sender) {
                if (!isset($senderReferences[$sender])) {
                    throw new LogicException(sprintf('Invalid Messenger routing configuration: the "%s" class is being routed to a sender called "%s". This is not a valid transport or service id.', $message, $sender));
                }
            }

            $messageToSendersMapping[$message] = $messageConfiguration['senders'];
        }

        $sendersServiceLocator = ServiceLocatorTagPass::register($container, $senderReferences);

        $container->getDefinition('messenger.senders_locator')
            ->replaceArgument(0, $messageToSendersMapping)
            ->replaceArgument(1, $sendersServiceLocator)
        ;

        $container->getDefinition('messenger.retry.send_failed_message_for_retry_listener')
            ->replaceArgument(0, $sendersServiceLocator)
        ;

        $container->getDefinition('messenger.retry_strategy_locator')
            ->replaceArgument(0, $transportRetryReferences);

        if (!$transportRateLimiterReferences) {
            $container->removeDefinition('messenger.rate_limiter_locator');
        } else {
            $container->getDefinition('messenger.rate_limiter_locator')
                ->replaceArgument(0, $transportRateLimiterReferences);
        }

        if (\count($failureTransports) > 0) {
            $container->getDefinition('console.command.messenger_failed_messages_retry')
                ->replaceArgument(0, $config['failure_transport']);
            $container->getDefinition('console.command.messenger_failed_messages_show')
                ->replaceArgument(0, $config['failure_transport']);
            $container->getDefinition('console.command.messenger_failed_messages_remove')
                ->replaceArgument(0, $config['failure_transport']);

            $failureTransportsByTransportNameServiceLocator = ServiceLocatorTagPass::register($container, $failureTransportReferencesByTransportName);
            $container->getDefinition('messenger.failure.send_failed_message_to_failure_transport_listener')
                ->replaceArgument(0, $failureTransportsByTransportNameServiceLocator);
        } else {
            $container->removeDefinition('messenger.failure.send_failed_message_to_failure_transport_listener');
            $container->removeDefinition('console.command.messenger_failed_messages_retry');
            $container->removeDefinition('console.command.messenger_failed_messages_show');
            $container->removeDefinition('console.command.messenger_failed_messages_remove');
        }

        if (!$container->hasDefinition('console.command.messenger_consume_messages')) {
            $container->removeDefinition('messenger.listener.reset_services');
        }
    }

    private function registerCacheConfiguration(array $config, ContainerBuilder $container): void
    {
        if (!class_exists(DefaultMarshaller::class)) {
            $container->removeDefinition('cache.default_marshaller');
        }

        $version = new Parameter('container.build_id');
        $container->getDefinition('cache.adapter.apcu')->replaceArgument(2, $version);
        $container->getDefinition('cache.adapter.system')->replaceArgument(2, $version);
        $container->getDefinition('cache.adapter.filesystem')->replaceArgument(2, $config['directory']);

        if (isset($config['prefix_seed'])) {
            $container->setParameter('cache.prefix.seed', $config['prefix_seed']);
        }
        if ($container->hasParameter('cache.prefix.seed')) {
            // Inline any env vars referenced in the parameter
            $container->setParameter('cache.prefix.seed', $container->resolveEnvPlaceholders($container->getParameter('cache.prefix.seed'), true));
        }
        foreach (['psr6', 'redis', 'memcached', 'doctrine_dbal', 'pdo'] as $name) {
            if (isset($config[$name = 'default_'.$name.'_provider'])) {
                $container->setAlias('cache.'.$name, new Alias(CachePoolPass::getServiceProvider($container, $config[$name]), false));
            }
        }
        foreach (['app', 'system'] as $name) {
            $config['pools']['cache.'.$name] = [
                'adapters' => [$config[$name]],
                'public' => true,
                'tags' => false,
            ];
        }
        foreach ($config['pools'] as $name => $pool) {
            $pool['adapters'] = $pool['adapters'] ?: ['cache.app'];

            $isRedisTagAware = ['cache.adapter.redis_tag_aware'] === $pool['adapters'];
            foreach ($pool['adapters'] as $provider => $adapter) {
                if (($config['pools'][$adapter]['adapters'] ?? null) === ['cache.adapter.redis_tag_aware']) {
                    $isRedisTagAware = true;
                } elseif ($config['pools'][$adapter]['tags'] ?? false) {
                    $pool['adapters'][$provider] = $adapter = '.'.$adapter.'.inner';
                }
            }

            if (1 === \count($pool['adapters'])) {
                if (!isset($pool['provider']) && !\is_int($provider)) {
                    $pool['provider'] = $provider;
                }
                $definition = new ChildDefinition($adapter);
            } else {
                $definition = new Definition(ChainAdapter::class, [$pool['adapters'], 0]);
                $pool['reset'] = 'reset';
            }

            if ($isRedisTagAware && 'cache.app' === $name) {
                $container->setAlias('cache.app.taggable', $name);
                $definition->addTag('cache.taggable', ['pool' => $name]);
            } elseif ($isRedisTagAware) {
                $tagAwareId = $name;
                $container->setAlias('.'.$name.'.inner', $name);
                $definition->addTag('cache.taggable', ['pool' => $name]);
            } elseif ($pool['tags']) {
                if (true !== $pool['tags'] && ($config['pools'][$pool['tags']]['tags'] ?? false)) {
                    $pool['tags'] = '.'.$pool['tags'].'.inner';
                }
                $container->register($name, TagAwareAdapter::class)
                    ->addArgument(new Reference('.'.$name.'.inner'))
                    ->addArgument(true !== $pool['tags'] ? new Reference($pool['tags']) : null)
                    ->setPublic($pool['public'])
                    ->addTag('cache.taggable', ['pool' => $name])
                ;

                if (method_exists(TagAwareAdapter::class, 'setLogger')) {
                    $container
                        ->getDefinition($name)
                        ->addMethodCall('setLogger', [new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)])
                        ->addTag('monolog.logger', ['channel' => 'cache']);
                }

                $pool['name'] = $tagAwareId = $name;
                $pool['public'] = false;
                $name = '.'.$name.'.inner';
            } elseif (!\in_array($name, ['cache.app', 'cache.system'], true)) {
                $tagAwareId = '.'.$name.'.taggable';
                $container->register($tagAwareId, TagAwareAdapter::class)
                    ->addArgument(new Reference($name))
                    ->addTag('cache.taggable', ['pool' => $name])
                ;
            }

            if (!\in_array($name, ['cache.app', 'cache.system'], true)) {
                $container->registerAliasForArgument($tagAwareId, TagAwareCacheInterface::class, $pool['name'] ?? $name);
                $container->registerAliasForArgument($name, CacheInterface::class, $pool['name'] ?? $name);
                $container->registerAliasForArgument($name, CacheItemPoolInterface::class, $pool['name'] ?? $name);
            }

            $definition->setPublic($pool['public']);
            unset($pool['adapters'], $pool['public'], $pool['tags']);

            $definition->addTag('cache.pool', $pool);
            $container->setDefinition($name, $definition);
        }

        if (method_exists(PropertyAccessor::class, 'createCache')) {
            $propertyAccessDefinition = $container->register('cache.property_access', AdapterInterface::class);
            $propertyAccessDefinition->setPublic(false);

            if (!$container->getParameter('kernel.debug')) {
                $propertyAccessDefinition->setFactory([PropertyAccessor::class, 'createCache']);
                $propertyAccessDefinition->setArguments(['', 0, $version, new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]);
                $propertyAccessDefinition->addTag('cache.pool', ['clearer' => 'cache.system_clearer']);
                $propertyAccessDefinition->addTag('monolog.logger', ['channel' => 'cache']);
            } else {
                $propertyAccessDefinition->setClass(ArrayAdapter::class);
                $propertyAccessDefinition->setArguments([0, false]);
            }
        }
    }

    private function registerHttpClientConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('http_client.php');

        $options = $config['default_options'] ?? [];
        $retryOptions = $options['retry_failed'] ?? ['enabled' => false];
        unset($options['retry_failed']);
        $container->getDefinition('http_client')->setArguments([$options, $config['max_host_connections'] ?? 6]);

        if (!$hasPsr18 = ContainerBuilder::willBeAvailable('psr/http-client', ClientInterface::class, ['symfony/framework-bundle', 'symfony/http-client'])) {
            $container->removeDefinition('psr18.http_client');
            $container->removeAlias(ClientInterface::class);
        }

        if (!ContainerBuilder::willBeAvailable('php-http/httplug', HttpClient::class, ['symfony/framework-bundle', 'symfony/http-client'])) {
            $container->removeDefinition(HttpClient::class);
        }

        if ($this->readConfigEnabled('http_client.retry_failed', $container, $retryOptions)) {
            $this->registerRetryableHttpClient($retryOptions, 'http_client', $container);
        }

        $httpClientId = ($retryOptions['enabled'] ?? false) ? 'http_client.retryable.inner' : ($this->isInitializedConfigEnabled('profiler') ? '.debug.http_client.inner' : 'http_client');
        foreach ($config['scoped_clients'] as $name => $scopeConfig) {
            if ('http_client' === $name) {
                throw new InvalidArgumentException(sprintf('Invalid scope name: "%s" is reserved.', $name));
            }

            $scope = $scopeConfig['scope'] ?? null;
            unset($scopeConfig['scope']);
            $retryOptions = $scopeConfig['retry_failed'] ?? ['enabled' => false];
            unset($scopeConfig['retry_failed']);

            if (null === $scope) {
                $baseUri = $scopeConfig['base_uri'];
                unset($scopeConfig['base_uri']);

                $container->register($name, ScopingHttpClient::class)
                    ->setFactory([ScopingHttpClient::class, 'forBaseUri'])
                    ->setArguments([new Reference($httpClientId), $baseUri, $scopeConfig])
                    ->addTag('http_client.client')
                ;
            } else {
                $container->register($name, ScopingHttpClient::class)
                    ->setArguments([new Reference($httpClientId), [$scope => $scopeConfig], $scope])
                    ->addTag('http_client.client')
                ;
            }

            if ($this->readConfigEnabled('http_client.scoped_clients.'.$name.'retry_failed', $container, $retryOptions)) {
                $this->registerRetryableHttpClient($retryOptions, $name, $container);
            }

            $container->registerAliasForArgument($name, HttpClientInterface::class);

            if ($hasPsr18) {
                $container->setDefinition('psr18.'.$name, new ChildDefinition('psr18.http_client'))
                    ->replaceArgument(0, new Reference($name));

                $container->registerAliasForArgument('psr18.'.$name, ClientInterface::class, $name);
            }
        }

        if ($responseFactoryId = $config['mock_response_factory'] ?? null) {
            $container->register($httpClientId.'.mock_client', MockHttpClient::class)
                ->setDecoratedService($httpClientId, null, -10) // lower priority than TraceableHttpClient
                ->setArguments([new Reference($responseFactoryId)]);
        }
    }

    private function registerRetryableHttpClient(array $options, string $name, ContainerBuilder $container): void
    {
        if (null !== $options['retry_strategy']) {
            $retryStrategy = new Reference($options['retry_strategy']);
        } else {
            $retryStrategy = new ChildDefinition('http_client.abstract_retry_strategy');
            $codes = [];
            foreach ($options['http_codes'] as $code => $codeOptions) {
                if ($codeOptions['methods']) {
                    $codes[$code] = $codeOptions['methods'];
                } else {
                    $codes[] = $code;
                }
            }

            $retryStrategy
                ->replaceArgument(0, $codes ?: GenericRetryStrategy::DEFAULT_RETRY_STATUS_CODES)
                ->replaceArgument(1, $options['delay'])
                ->replaceArgument(2, $options['multiplier'])
                ->replaceArgument(3, $options['max_delay'])
                ->replaceArgument(4, $options['jitter']);
            $container->setDefinition($name.'.retry_strategy', $retryStrategy);

            $retryStrategy = new Reference($name.'.retry_strategy');
        }

        $container
            ->register($name.'.retryable', RetryableHttpClient::class)
            ->setDecoratedService($name, null, 10) // higher priority than TraceableHttpClient
            ->setArguments([new Reference($name.'.retryable.inner'), $retryStrategy, $options['max_retries'], new Reference('logger')])
            ->addTag('monolog.logger', ['channel' => 'http_client']);
    }

    private function registerMailerConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!class_exists(Mailer::class)) {
            throw new LogicException('Mailer support cannot be enabled as the component is not installed. Try running "composer require symfony/mailer".');
        }

        $loader->load('mailer.php');
        $loader->load('mailer_transports.php');
        if (!\count($config['transports']) && null === $config['dsn']) {
            $config['dsn'] = 'smtp://null';
        }
        $transports = $config['dsn'] ? ['main' => $config['dsn']] : $config['transports'];
        $container->getDefinition('mailer.transports')->setArgument(0, $transports);
        $container->getDefinition('mailer.default_transport')->setArgument(0, current($transports));

        $mailer = $container->getDefinition('mailer.mailer');
        if (false === $messageBus = $config['message_bus']) {
            $mailer->replaceArgument(1, null);
        } else {
            $mailer->replaceArgument(1, $messageBus ? new Reference($messageBus) : new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE));
        }

        $classToServices = [
            GmailTransportFactory::class => 'mailer.transport_factory.gmail',
            InfobipMailerTransportFactory::class => 'mailer.transport_factory.infobip',
            MailgunTransportFactory::class => 'mailer.transport_factory.mailgun',
            MailjetTransportFactory::class => 'mailer.transport_factory.mailjet',
            MailPaceTransportFactory::class => 'mailer.transport_factory.mailpace',
            MandrillTransportFactory::class => 'mailer.transport_factory.mailchimp',
            OhMySmtpTransportFactory::class => 'mailer.transport_factory.ohmysmtp',
            PostmarkTransportFactory::class => 'mailer.transport_factory.postmark',
            SendgridTransportFactory::class => 'mailer.transport_factory.sendgrid',
            SendinblueTransportFactory::class => 'mailer.transport_factory.sendinblue',
            SesTransportFactory::class => 'mailer.transport_factory.amazon',
        ];

        foreach ($classToServices as $class => $service) {
            $package = substr($service, \strlen('mailer.transport_factory.'));

            if (!ContainerBuilder::willBeAvailable(sprintf('symfony/%s-mailer', 'gmail' === $package ? 'google' : $package), $class, ['symfony/framework-bundle', 'symfony/mailer'])) {
                $container->removeDefinition($service);
            }
        }

        $envelopeListener = $container->getDefinition('mailer.envelope_listener');
        $envelopeListener->setArgument(0, $config['envelope']['sender'] ?? null);
        $envelopeListener->setArgument(1, $config['envelope']['recipients'] ?? null);

        if ($config['headers']) {
            $headers = new Definition(Headers::class);
            foreach ($config['headers'] as $name => $data) {
                $value = $data['value'];
                if (\in_array(strtolower($name), ['from', 'to', 'cc', 'bcc', 'reply-to'])) {
                    $value = (array) $value;
                }
                $headers->addMethodCall('addHeader', [$name, $value]);
            }
            $messageListener = $container->getDefinition('mailer.message_listener');
            $messageListener->setArgument(0, $headers);
        } else {
            $container->removeDefinition('mailer.message_listener');
        }

        if (!class_exists(MessengerTransportListener::class)) {
            $container->removeDefinition('mailer.messenger_transport_listener');
        }
    }

    private function registerNotifierConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        if (!class_exists(Notifier::class)) {
            throw new LogicException('Notifier support cannot be enabled as the component is not installed. Try running "composer require symfony/notifier".');
        }

        $loader->load('notifier.php');
        $loader->load('notifier_transports.php');

        if ($config['chatter_transports']) {
            $container->getDefinition('chatter.transports')->setArgument(0, $config['chatter_transports']);
        } else {
            $container->removeDefinition('chatter');
            $container->removeAlias(ChatterInterface::class);
        }
        if ($config['texter_transports']) {
            $container->getDefinition('texter.transports')->setArgument(0, $config['texter_transports']);
        } else {
            $container->removeDefinition('texter');
            $container->removeAlias(TexterInterface::class);
        }

        if ($this->isInitializedConfigEnabled('mailer')) {
            $sender = $container->getDefinition('mailer.envelope_listener')->getArgument(0);
            $container->getDefinition('notifier.channel.email')->setArgument(2, $sender);
        } else {
            $container->removeDefinition('notifier.channel.email');
        }

        foreach (['texter', 'chatter', 'notifier.channel.chat', 'notifier.channel.email', 'notifier.channel.sms'] as $serviceId) {
            if (!$container->hasDefinition($serviceId)) {
                continue;
            }

            if (false === $messageBus = $config['message_bus']) {
                $container->getDefinition($serviceId)->replaceArgument(1, null);
            } else {
                $container->getDefinition($serviceId)->replaceArgument(1, $messageBus ? new Reference($messageBus) : new Reference('messenger.default_bus', ContainerInterface::NULL_ON_INVALID_REFERENCE));
            }
        }

        if ($this->isInitializedConfigEnabled('messenger')) {
            if ($config['notification_on_failed_messages']) {
                $container->getDefinition('notifier.failed_message_listener')->addTag('kernel.event_subscriber');
            }

            // as we have a bus, the channels don't need the transports
            $container->getDefinition('notifier.channel.chat')->setArgument(0, null);
            if ($container->hasDefinition('notifier.channel.email')) {
                $container->getDefinition('notifier.channel.email')->setArgument(0, null);
            }
            $container->getDefinition('notifier.channel.sms')->setArgument(0, null);
            $container->getDefinition('notifier.channel.push')->setArgument(0, null);
        }

        $container->getDefinition('notifier.channel_policy')->setArgument(0, $config['channel_policy']);

        $container->registerForAutoconfiguration(NotifierTransportFactoryInterface::class)
            ->addTag('chatter.transport_factory');

        $container->registerForAutoconfiguration(NotifierTransportFactoryInterface::class)
            ->addTag('texter.transport_factory');

        $classToServices = [
            AllMySmsTransportFactory::class => 'notifier.transport_factory.all-my-sms',
            AmazonSnsTransportFactory::class => 'notifier.transport_factory.amazon-sns',
            BandwidthTransportFactory::class => 'notifier.transport_factory.bandwidth',
            ChatworkTransportFactory::class => 'notifier.transport_factory.chatwork',
            ClickatellTransportFactory::class => 'notifier.transport_factory.clickatell',
            ContactEveryoneTransportFactory::class => 'notifier.transport_factory.contact-everyone',
            DiscordTransportFactory::class => 'notifier.transport_factory.discord',
            EngagespotTransportFactory::class => 'notifier.transport_factory.engagespot',
            EsendexTransportFactory::class => 'notifier.transport_factory.esendex',
            ExpoTransportFactory::class => 'notifier.transport_factory.expo',
            FakeChatTransportFactory::class => 'notifier.transport_factory.fake-chat',
            FakeSmsTransportFactory::class => 'notifier.transport_factory.fake-sms',
            FirebaseTransportFactory::class => 'notifier.transport_factory.firebase',
            FortySixElksTransportFactory::class => 'notifier.transport_factory.forty-six-elks',
            FreeMobileTransportFactory::class => 'notifier.transport_factory.free-mobile',
            GatewayApiTransportFactory::class => 'notifier.transport_factory.gateway-api',
            GitterTransportFactory::class => 'notifier.transport_factory.gitter',
            GoogleChatTransportFactory::class => 'notifier.transport_factory.google-chat',
            InfobipTransportFactory::class => 'notifier.transport_factory.infobip',
            IqsmsTransportFactory::class => 'notifier.transport_factory.iqsms',
            IsendproTransportFactory::class => 'notifier.transport_factory.isendpro',
            KazInfoTehTransportFactory::class => 'notifier.transport_factory.kaz-info-teh',
            LightSmsTransportFactory::class => 'notifier.transport_factory.light-sms',
            LineNotifyTransportFactory::class => 'notifier.transport_factory.line-notify',
            LinkedInTransportFactory::class => 'notifier.transport_factory.linked-in',
            MailjetNotifierTransportFactory::class => 'notifier.transport_factory.mailjet',
            MastodonTransportFactory::class => 'notifier.transport_factory.mastodon',
            MattermostTransportFactory::class => 'notifier.transport_factory.mattermost',
            MercureTransportFactory::class => 'notifier.transport_factory.mercure',
            MessageBirdTransport::class => 'notifier.transport_factory.message-bird',
            MessageMediaTransportFactory::class => 'notifier.transport_factory.message-media',
            MicrosoftTeamsTransportFactory::class => 'notifier.transport_factory.microsoft-teams',
            MobytTransportFactory::class => 'notifier.transport_factory.mobyt',
            OctopushTransportFactory::class => 'notifier.transport_factory.octopush',
            OneSignalTransportFactory::class => 'notifier.transport_factory.one-signal',
            OrangeSmsTransportFactory::class => 'notifier.transport_factory.orange-sms',
            OvhCloudTransportFactory::class => 'notifier.transport_factory.ovh-cloud',
            PagerDutyTransportFactory::class => 'notifier.transport_factory.pager-duty',
            PlivoTransportFactory::class => 'notifier.transport_factory.plivo',
            RingCentralTransportFactory::class => 'notifier.transport_factory.ring-central',
            RocketChatTransportFactory::class => 'notifier.transport_factory.rocket-chat',
            SendberryTransportFactory::class => 'notifier.transport_factory.sendberry',
            SendinblueNotifierTransportFactory::class => 'notifier.transport_factory.sendinblue',
            SinchTransportFactory::class => 'notifier.transport_factory.sinch',
            SlackTransportFactory::class => 'notifier.transport_factory.slack',
            Sms77TransportFactory::class => 'notifier.transport_factory.sms77',
            SmsapiTransportFactory::class => 'notifier.transport_factory.smsapi',
            SmsBiurasTransportFactory::class => 'notifier.transport_factory.sms-biuras',
            SmscTransportFactory::class => 'notifier.transport_factory.smsc',
            SmsFactorTransportFactory::class => 'notifier.transport_factory.sms-factor',
            SpotHitTransportFactory::class => 'notifier.transport_factory.spot-hit',
            TelegramTransportFactory::class => 'notifier.transport_factory.telegram',
            TelnyxTransportFactory::class => 'notifier.transport_factory.telnyx',
            TermiiTransportFactory::class => 'notifier.transport_factory.termii',
            TurboSmsTransport::class => 'notifier.transport_factory.turbo-sms',
            TwilioTransportFactory::class => 'notifier.transport_factory.twilio',
            TwitterTransportFactory::class => 'notifier.transport_factory.twitter',
            VonageTransportFactory::class => 'notifier.transport_factory.vonage',
            YunpianTransportFactory::class => 'notifier.transport_factory.yunpian',
            ZendeskTransportFactory::class => 'notifier.transport_factory.zendesk',
            ZulipTransportFactory::class => 'notifier.transport_factory.zulip',
        ];

        $parentPackages = ['symfony/framework-bundle', 'symfony/notifier'];

        foreach ($classToServices as $class => $service) {
            $package = substr($service, \strlen('notifier.transport_factory.'));

            if (!ContainerBuilder::willBeAvailable(sprintf('symfony/%s-notifier', $package), $class, $parentPackages)) {
                $container->removeDefinition($service);
            }
        }

        if (ContainerBuilder::willBeAvailable('symfony/mercure-notifier', MercureTransportFactory::class, $parentPackages) && ContainerBuilder::willBeAvailable('symfony/mercure-bundle', MercureBundle::class, $parentPackages) && \in_array(MercureBundle::class, $container->getParameter('kernel.bundles'), true)) {
            $container->getDefinition($classToServices[MercureTransportFactory::class])
                ->replaceArgument('$registry', new Reference(HubRegistry::class));
        } elseif (ContainerBuilder::willBeAvailable('symfony/mercure-notifier', MercureTransportFactory::class, $parentPackages)) {
            $container->removeDefinition($classToServices[MercureTransportFactory::class]);
        }

        if (ContainerBuilder::willBeAvailable('symfony/fake-chat-notifier', FakeChatTransportFactory::class, ['symfony/framework-bundle', 'symfony/notifier', 'symfony/mailer'])) {
            $container->getDefinition($classToServices[FakeChatTransportFactory::class])
                ->replaceArgument('$mailer', new Reference('mailer'))
                ->replaceArgument('$logger', new Reference('logger'));
        }

        if (ContainerBuilder::willBeAvailable('symfony/fake-sms-notifier', FakeSmsTransportFactory::class, ['symfony/framework-bundle', 'symfony/notifier', 'symfony/mailer'])) {
            $container->getDefinition($classToServices[FakeSmsTransportFactory::class])
                ->replaceArgument('$mailer', new Reference('mailer'))
                ->replaceArgument('$logger', new Reference('logger'));
        }

        if (isset($config['admin_recipients'])) {
            $notifier = $container->getDefinition('notifier');
            foreach ($config['admin_recipients'] as $i => $recipient) {
                $id = 'notifier.admin_recipient.'.$i;
                $container->setDefinition($id, new Definition(Recipient::class, [$recipient['email'], $recipient['phone']]));
                $notifier->addMethodCall('addAdminRecipient', [new Reference($id)]);
            }
        }
    }

    private function registerRateLimiterConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('rate_limiter.php');

        foreach ($config['limiters'] as $name => $limiterConfig) {
            // default configuration (when used by other DI extensions)
            $limiterConfig += ['lock_factory' => 'lock.factory', 'cache_pool' => 'cache.rate_limiter'];

            $limiter = $container->setDefinition($limiterId = 'limiter.'.$name, new ChildDefinition('limiter'));

            if (null !== $limiterConfig['lock_factory']) {
                if (!interface_exists(LockInterface::class)) {
                    throw new LogicException(sprintf('Rate limiter "%s" requires the Lock component to be installed. Try running "composer require symfony/lock".', $name));
                }

                if (!$this->isInitializedConfigEnabled('lock')) {
                    throw new LogicException(sprintf('Rate limiter "%s" requires the Lock component to be configured.', $name));
                }

                $limiter->replaceArgument(2, new Reference($limiterConfig['lock_factory']));
            }
            unset($limiterConfig['lock_factory']);

            if (null === $storageId = $limiterConfig['storage_service'] ?? null) {
                $container->register($storageId = 'limiter.storage.'.$name, CacheStorage::class)->addArgument(new Reference($limiterConfig['cache_pool']));
            }

            $limiter->replaceArgument(1, new Reference($storageId));
            unset($limiterConfig['storage_service'], $limiterConfig['cache_pool']);

            $limiterConfig['id'] = $name;
            $limiter->replaceArgument(0, $limiterConfig);

            $container->registerAliasForArgument($limiterId, RateLimiterFactory::class, $name.'.limiter');
        }
    }

    /**
     * @deprecated since Symfony 6.2
     *
     * @return void
     */
    public static function registerRateLimiter(ContainerBuilder $container, string $name, array $limiterConfig)
    {
        trigger_deprecation('symfony/framework-bundle', '6.2', 'The "%s()" method is deprecated.', __METHOD__);

        // default configuration (when used by other DI extensions)
        $limiterConfig += ['lock_factory' => 'lock.factory', 'cache_pool' => 'cache.rate_limiter'];

        $limiter = $container->setDefinition($limiterId = 'limiter.'.$name, new ChildDefinition('limiter'));

        if (null !== $limiterConfig['lock_factory']) {
            if (!interface_exists(LockInterface::class)) {
                throw new LogicException(sprintf('Rate limiter "%s" requires the Lock component to be installed. Try running "composer require symfony/lock".', $name));
            }
            if (!$container->hasDefinition('lock.factory.abstract')) {
                throw new LogicException(sprintf('Rate limiter "%s" requires the Lock component to be configured.', $name));
            }

            $limiter->replaceArgument(2, new Reference($limiterConfig['lock_factory']));
        }
        unset($limiterConfig['lock_factory']);

        if (null === $storageId = $limiterConfig['storage_service'] ?? null) {
            $container->register($storageId = 'limiter.storage.'.$name, CacheStorage::class)->addArgument(new Reference($limiterConfig['cache_pool']));
        }

        $limiter->replaceArgument(1, new Reference($storageId));
        unset($limiterConfig['storage_service'], $limiterConfig['cache_pool']);

        $limiterConfig['id'] = $name;
        $limiter->replaceArgument(0, $limiterConfig);

        $container->registerAliasForArgument($limiterId, RateLimiterFactory::class, $name.'.limiter');
    }

    private function registerUidConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('uid.php');

        $container->getDefinition('uuid.factory')
            ->setArguments([
                $config['default_uuid_version'],
                $config['time_based_uuid_version'],
                $config['name_based_uuid_version'],
                UuidV4::class,
                $config['time_based_uuid_node'] ?? null,
                $config['name_based_uuid_namespace'] ?? null,
            ])
        ;

        if (isset($config['name_based_uuid_namespace'])) {
            $container->getDefinition('name_based_uuid.factory')
                ->setArguments([$config['name_based_uuid_namespace']]);
        }

        if (!class_exists(UidValueResolver::class)) {
            $container->removeDefinition('argument_resolver.uid');
        }
    }

    private function registerHtmlSanitizerConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader): void
    {
        $loader->load('html_sanitizer.php');

        foreach ($config['sanitizers'] as $sanitizerName => $sanitizerConfig) {
            $configId = 'html_sanitizer.config.'.$sanitizerName;
            $def = $container->register($configId, HtmlSanitizerConfig::class);

            // Base
            if ($sanitizerConfig['allow_safe_elements']) {
                $def->addMethodCall('allowSafeElements', [], true);
            }

            if ($sanitizerConfig['allow_static_elements']) {
                $def->addMethodCall('allowStaticElements', [], true);
            }

            // Configures elements
            foreach ($sanitizerConfig['allow_elements'] as $element => $attributes) {
                $def->addMethodCall('allowElement', [$element, $attributes], true);
            }

            foreach ($sanitizerConfig['block_elements'] as $element) {
                $def->addMethodCall('blockElement', [$element], true);
            }

            foreach ($sanitizerConfig['drop_elements'] as $element) {
                $def->addMethodCall('dropElement', [$element], true);
            }

            // Configures attributes
            foreach ($sanitizerConfig['allow_attributes'] as $attribute => $elements) {
                $def->addMethodCall('allowAttribute', [$attribute, $elements], true);
            }

            foreach ($sanitizerConfig['drop_attributes'] as $attribute => $elements) {
                $def->addMethodCall('dropAttribute', [$attribute, $elements], true);
            }

            // Force attributes
            foreach ($sanitizerConfig['force_attributes'] as $element => $attributes) {
                foreach ($attributes as $attrName => $attrValue) {
                    $def->addMethodCall('forceAttribute', [$element, $attrName, $attrValue], true);
                }
            }

            // Settings
            $def->addMethodCall('forceHttpsUrls', [$sanitizerConfig['force_https_urls']], true);
            if ($sanitizerConfig['allowed_link_schemes']) {
                $def->addMethodCall('allowLinkSchemes', [$sanitizerConfig['allowed_link_schemes']], true);
            }
            $def->addMethodCall('allowLinkHosts', [$sanitizerConfig['allowed_link_hosts']], true);
            $def->addMethodCall('allowRelativeLinks', [$sanitizerConfig['allow_relative_links']], true);
            if ($sanitizerConfig['allowed_media_schemes']) {
                $def->addMethodCall('allowMediaSchemes', [$sanitizerConfig['allowed_media_schemes']], true);
            }
            $def->addMethodCall('allowMediaHosts', [$sanitizerConfig['allowed_media_hosts']], true);
            $def->addMethodCall('allowRelativeMedias', [$sanitizerConfig['allow_relative_medias']], true);

            // Custom attribute sanitizers
            foreach ($sanitizerConfig['with_attribute_sanitizers'] as $serviceName) {
                $def->addMethodCall('withAttributeSanitizer', [new Reference($serviceName)], true);
            }

            foreach ($sanitizerConfig['without_attribute_sanitizers'] as $serviceName) {
                $def->addMethodCall('withoutAttributeSanitizer', [new Reference($serviceName)], true);
            }

            if ($sanitizerConfig['max_input_length']) {
                $def->addMethodCall('withMaxInputLength', [$sanitizerConfig['max_input_length']], true);
            }

            // Create the sanitizer and link its config
            $sanitizerId = 'html_sanitizer.sanitizer.'.$sanitizerName;
            $container->register($sanitizerId, HtmlSanitizer::class)
                ->addTag('html_sanitizer', ['sanitizer' => $sanitizerName])
                ->addArgument(new Reference($configId));

            if ('default' !== $sanitizerName) {
                $container->registerAliasForArgument($sanitizerId, HtmlSanitizerInterface::class, $sanitizerName);
            }
        }
    }

    private function resolveTrustedHeaders(array $headers): int
    {
        $trustedHeaders = 0;

        foreach ($headers as $h) {
            $trustedHeaders |= match ($h) {
                'forwarded' => Request::HEADER_FORWARDED,
                'x-forwarded-for' => Request::HEADER_X_FORWARDED_FOR,
                'x-forwarded-host' => Request::HEADER_X_FORWARDED_HOST,
                'x-forwarded-proto' => Request::HEADER_X_FORWARDED_PROTO,
                'x-forwarded-port' => Request::HEADER_X_FORWARDED_PORT,
                'x-forwarded-prefix' => Request::HEADER_X_FORWARDED_PREFIX,
                default => 0,
            };
        }

        return $trustedHeaders;
    }

    public function getXsdValidationBasePath(): string|false
    {
        return \dirname(__DIR__).'/Resources/config/schema';
    }

    public function getNamespace(): string
    {
        return 'http://symfony.com/schema/dic/symfony';
    }

    protected function isConfigEnabled(ContainerBuilder $container, array $config): bool
    {
        throw new \LogicException('To prevent using outdated configuration, you must use the "readConfigEnabled" method instead.');
    }

    private function isInitializedConfigEnabled(string $path): bool
    {
        if (isset($this->configsEnabled[$path])) {
            return $this->configsEnabled[$path];
        }

        throw new LogicException(sprintf('Can not read config enabled at "%s" because it has not been initialized.', $path));
    }

    private function readConfigEnabled(string $path, ContainerBuilder $container, array $config): bool
    {
        return $this->configsEnabled[$path] ??= parent::isConfigEnabled($container, $config);
    }

    private function writeConfigEnabled(string $path, bool $value, array &$config): void
    {
        if (isset($this->configsEnabled[$path])) {
            throw new LogicException('Can not change config enabled because it has already been read.');
        }

        $this->configsEnabled[$path] = $value;
        $config['enabled'] = $value;
    }
}
