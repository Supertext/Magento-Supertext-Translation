<?php

/**
 * @package     Supertext Translation for Magento
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

declare(strict_types=1);

namespace Supertext\Translation\Console\Command;

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Supertext\Translation\Api\SupertextException;
use Supertext\Translation\Model\Config;
use Supertext\Translation\Model\Translation\EntityTypes;
use Supertext\Translation\Model\Translation\Translator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento supertext:translate product 1 2 --store=de_ch --store=fr_ch [--overwrite]
 *
 * Same translation as the admin. Without --store: every store view whose language differs
 * from the default locale.
 */
class TranslateCommand extends Command
{
    public function __construct(
        private readonly State $state,
        private readonly Translator $translator,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('supertext:translate')
            ->setDescription('Translate products, categories, CMS pages or blocks with Supertext')
            ->addArgument('type', InputArgument::REQUIRED, 'product, category, cms_page or cms_block')
            ->addArgument('ids', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Ids')
            ->addOption('store', 's', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Store view code or id; default: all store views in another language')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Replace existing translations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode(Area::AREA_ADMINHTML);
        } catch (LocalizedException) {
            // Already set.
        }

        $type = (string) $input->getArgument('type');

        if (!isset(EntityTypes::TYPES[$type])) {
            $output->writeln('<error>Type must be product, category, cms_page or cms_block.</error>');

            return Command::INVALID;
        }

        $output->writeln(sprintf('Supertext Translation %s', $this->config->version()));

        $stores = [];

        if ($input->getOption('store')) {
            foreach ($input->getOption('store') as $code) {
                try {
                    $stores[] = (int) $this->storeManager->getStore(ctype_digit((string) $code) ? (int) $code : (string) $code)->getId();
                } catch (\Throwable) {
                    $output->writeln(sprintf('<error>Unknown store view "%s".</error>', $code));

                    return Command::INVALID;
                }
            }
        } else {
            foreach ($this->config->storeViews() as $view) {
                if (!$view['sameLanguage']) {
                    $stores[] = $view['id'];
                }
            }
        }

        $failed = 0;

        foreach (EntityTypes::ids($input->getArgument('ids')) as $id) {
            foreach ($stores as $storeId) {
                $label = sprintf('%s %d → %s (%s)', $type, $id, $this->storeManager->getStore($storeId)->getCode(), $this->config->targetCode($storeId));

                try {
                    $result = $this->translator->translate($type, $id, $storeId, (bool) $input->getOption('overwrite'));
                    $output->writeln($result['status'] === 'unchanged'
                        ? sprintf('%s: skipped, already translated (use --overwrite)', $label)
                        : sprintf('%s: %s %d fields, kept %d', $label, $result['status'], \count($result['translated']), \count($result['kept'])));

                    foreach ($result['notes'] as $note) {
                        $output->writeln('  ' . $note);
                    }
                } catch (SupertextException|LocalizedException $e) {
                    ++$failed;
                    $output->writeln(sprintf('<error>%s: %s</error>', $label, $e->getMessage()));
                }
            }
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
