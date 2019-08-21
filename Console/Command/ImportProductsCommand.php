<?php

namespace Sagital\ProductImporter\Console\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\State;
use Magento\Framework\ObjectManagerInterface;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportProductsCommand extends Command
{

    /**
     * @var ObjectManagerInterface $objectManager
     */
    protected $objectManager;

    /**
     * @var LoggerInterface $logger ;
     */
    protected $logger;

    /**
     * @var State $state
     */
    protected $state;

    const FILE_ARGUMENT = 'File';
    const IMAGES_PATH_ARGUMENT = 'Images Path';

    public function __construct(ObjectManagerInterface $objectManager, LoggerInterface $logger, State $state)
    {
        $this->logger = $logger;
        $this->state = $state;
        $this->objectManager = $objectManager;

        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('sagital:import-products')
            ->setDescription('Import products from a CSV file')
            ->setDefinition([
                new InputArgument(
                    self::FILE_ARGUMENT,
                    InputArgument::REQUIRED,
                    'Products CSV file location'
                ),
                new InputArgument(
                    self::IMAGES_PATH_ARGUMENT,
                    InputArgument::OPTIONAL,
                    'The location of the images folder',
                    'pub/media/import/'
                ),

            ]);

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->state->setAreaCode('adminhtml');
        $filesystem = $this->objectManager
            ->create(\Magento\Framework\Filesystem::class);

        $file = $input->getArgument(self::FILE_ARGUMENT);
        $imagesPath = $input->getArgument(self::IMAGES_PATH_ARGUMENT);

        $directory = $filesystem->getDirectoryWrite(DirectoryList::ROOT);

        $source = $this->objectManager->create(
            \Magento\ImportExport\Model\Import\Source\Csv::class,
            [
                'file' => $file,
                'directory' => $directory
            ]
        );

        $import = $this->objectManager->create(
            Import::class,
            ['logger' => $this->logger]
        );

        $import->addData(
            ['behavior' => Import::BEHAVIOR_ADD_UPDATE,
                Import::FIELD_NAME_IMG_FILE_DIR => $imagesPath,
                Import::FIELD_NAME_VALIDATION_STRATEGY => ProcessingErrorAggregatorInterface::VALIDATION_STRATEGY_STOP_ON_ERROR,
                'entity' => 'catalog_product']
        )
            ->validateSource($source);



        try {
            $import->importSource();
        } catch (Exception $e) {
            $output->writeln($e->getMessage());
        }

        $logTrace = $import->getFormatedLogTrace();

        $output->writeln($logTrace);
    }
}
