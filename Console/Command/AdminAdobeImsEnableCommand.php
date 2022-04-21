<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\AdminAdobeIms\Console\Command;

use Magento\AdminAdobeIms\Model\ImsConnection;
use Magento\AdminAdobeIms\Service\UpdateTokensService;
use Magento\AdminAdobeIms\Service\ImsCommandOptionService;
use Magento\AdminAdobeIms\Service\ImsConfig;
use Magento\Framework\App\Cache\Type\Config;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\InvalidArgumentException;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to set Admin Adobe IMS Module mode
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AdminAdobeImsEnableCommand extends Command
{
    /**
     * Name of "organization-id" input option
     */
    private const ORGANIZATION_ID_ARGUMENT = 'organization-id';

    /**
     * Name of "client-id" input option
     */
    private const CLIENT_ID_ARGUMENT = 'client-id';

    /**
     * Name of "client-secret" input option
     */
    private const CLIENT_SECRET_ARGUMENT = 'client-secret';

    /**
     * @var ImsConfig
     */
    private ImsConfig $imsConfig;

    /**
     * @var ImsConnection
     */
    private ImsConnection $imsConnection;

    /**
     * @var ImsCommandOptionService
     */
    private ImsCommandOptionService $imsCommandOptionService;

    /**
     * @var TypeListInterface
     */
    private TypeListInterface $cacheTypeList;

    /**
     * @var UpdateTokensService
     */
    private UpdateTokensService $updateTokensService;

    /**
     * @param ImsConfig $imsConfig
     * @param ImsConnection $imsConnection
     * @param ImsCommandOptionService $imsCommandOptionService
     * @param TypeListInterface $cacheTypeList
     * @param UpdateTokensService $updateTokensService
     */
    public function __construct(
        ImsConfig $imsConfig,
        ImsConnection $imsConnection,
        ImsCommandOptionService $imsCommandOptionService,
        TypeListInterface $cacheTypeList,
        UpdateTokensService $updateTokensService
    ) {
        parent::__construct();
        $this->imsConfig = $imsConfig;
        $this->imsConnection = $imsConnection;
        $this->imsCommandOptionService = $imsCommandOptionService;
        $this->cacheTypeList = $cacheTypeList;
        $this->updateTokensService = $updateTokensService;

        $this->setName('admin:adobe-ims:enable')
            ->setDescription('Enable Adobe IMS Module.')
            ->setDefinition([
                new InputOption(
                    self::ORGANIZATION_ID_ARGUMENT,
                    'o',
                    InputOption::VALUE_OPTIONAL,
                    'Set Organization ID for Adobe IMS configuration. Required when enabling the module'
                ),
                new InputOption(
                    self::CLIENT_ID_ARGUMENT,
                    'c',
                    InputOption::VALUE_OPTIONAL,
                    'Set the client ID for Adobe IMS configuration. Required when enabling the module'
                ),
                new InputOption(
                    self::CLIENT_SECRET_ARGUMENT,
                    's',
                    InputOption::VALUE_OPTIONAL,
                    'Set the client Secret for Adobe IMS configuration. Required when enabling the module'
                )
            ]);
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): ?int
    {
        try {
            $helper = $this->getHelper('question');

            $organizationId = $this->imsCommandOptionService->getOrganizationId(
                $input,
                $output,
                $helper,
                self::ORGANIZATION_ID_ARGUMENT
            );

            $clientId = $this->imsCommandOptionService->getClientId(
                $input,
                $output,
                $helper,
                self::CLIENT_ID_ARGUMENT
            );

            $clientSecret = $this->imsCommandOptionService->getClientSecret(
                $input,
                $output,
                $helper,
                self::CLIENT_SECRET_ARGUMENT
            );

            if ($clientId && $clientSecret && $organizationId) {
                $enabled = $this->enableModule($clientId, $clientSecret, $organizationId);
                if ($enabled) {
                    $output->writeln(__('Admin Adobe IMS integration is enabled'));
                    return Cli::RETURN_SUCCESS;
                }
            }

            throw new LocalizedException(
                __('The Client ID, Client Secret and Organization ID are required ' .
                    'when enabling the Admin Adobe IMS Module')
            );
        } catch (\Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                $output->writeln($e->getTraceAsString());
            }
            return Cli::RETURN_FAILURE;
        }
    }

    /**
     * Enable Admin Adobe IMS Module when testConnection was successfully
     *
     * @param string $clientId
     * @param string $clientSecret
     * @param string $organizationId
     * @return bool
     * @throws InvalidArgumentException
     */
    private function enableModule(
        string $clientId,
        string $clientSecret,
        string $organizationId
    ): bool {
        $testAuth = $this->imsConnection->testAuth($clientId);
        if ($testAuth) {
            $this->imsConfig->enableModule($clientId, $clientSecret, $organizationId);
            $this->cacheTypeList->cleanType(Config::TYPE_IDENTIFIER);
            $this->updateTokensService->execute();
            return true;
        }

        return false;
    }
}
