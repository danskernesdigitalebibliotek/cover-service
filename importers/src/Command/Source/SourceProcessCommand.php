<?php

/**
 * @file
 * Reindex data in the search table base on vendor.
 */

namespace App\Command\Source;

use App\Entity\Source;
use App\Message\SearchMessage;
use App\Repository\SourceRepository;
use App\Service\VendorService\ProgressBarTrait;
use App\Utils\Types\VendorState;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:source:process',
    description: 'Process source table'
)]
class SourceProcessCommand extends Command
{
    use ProgressBarTrait;

    /**
     * SearchReindexCommand constructor.
     *
     * @param EntityManagerInterface $em
     * @param MessageBusInterface $bus
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus
    ) {
        parent::__construct();
    }

    /**
     * Define the command.
     */
    protected function configure(): void
    {
        $this->addOption('vendor-id', null, InputOption::VALUE_OPTIONAL, 'Limit the re-index to vendor with this id number')
            ->addOption('identifier', null, InputOption::VALUE_OPTIONAL, 'If set only this identifier will be re-index (requires that you set vendor id)')
            ->addOption('clean-up', null, InputOption::VALUE_NONE, 'Remove all rows from the search table related to a given source before insert')
            ->addOption('without-search-cache', null, InputOption::VALUE_NONE, 'If set do not use search cache during re-index')
            ->addOption('without-image', null, InputOption::VALUE_NONE, 'Include sources which do not have an image attached')
            ->addOption('last-indexed-date', null, InputOption::VALUE_OPTIONAL, 'The date used when re-indexing in batches to have keeps track index by date (24-10-2021)')
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Limit the number of sources rows to index, requires last-indexed-date is given');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $vendorId = (int) $input->getOption('vendor-id');
        $cleanUp = $input->getOption('clean-up');
        $identifier = $input->getOption('identifier');
        $withOutSearchCache = $input->getOption('without-search-cache');
        $lastIndexedDate = $input->getOption('last-indexed-date');
        $limit = (int) $input->getOption('limit');
        $withoutImage = $input->getOption('without-image');

        if (!is_null($identifier)) {
            if (0 > $vendorId) {
                $output->writeln('<error>Missing vendor id required in combination with identifier</error>');

                return Command::FAILURE;
            }
        }

        $inputDate = null;
        if (!is_null($lastIndexedDate)) {
            $format = 'd-m-Y';
            $inputDate = \DateTimeImmutable::createFromFormat('!'.$format, $lastIndexedDate);
            if (false === $inputDate || $inputDate->format($format) !== $lastIndexedDate) {
                $output->writeln('<error>Last indexed date should have the format "d-m-Y"</error>');

                return Command::FAILURE;
            }
        }

        if (0 < $limit) {
            if (is_null($inputDate)) {
                $output->writeln('<error>Limit can not be given without last-indexed-date</error>');

                return Command::FAILURE;
            }
        }

        // Progress bar setup.
        $progressBarSheet = new ProgressBar($output);
        $progressBarSheet->setFormat('[%bar%] %elapsed% (%memory%) - %message%');
        $this->setProgressBar($progressBarSheet);
        $this->progressStart('Loading database source');

        /** @var SourceRepository $sourceRepos */
        $sourceRepos = $this->em->getRepository(Source::class);
        $query = $sourceRepos->findReindexabledSources($limit, $inputDate, $vendorId, $identifier, $withoutImage);
        $batchSize = 200;
        $i = 1;

        /* @var Source $source */
        foreach ($query->toIterable() as $source) {
            // Build and create new search job which will trigger index event.
            $message = new SearchMessage();
            $message->setIdentifier($source->getMatchId())
                ->setOperation(true === $cleanUp ? VendorState::DELETE_AND_UPDATE : VendorState::UPDATE)
                ->setIdentifierType($source->getMatchType())
                ->setVendorId($source->getVendor()->getId())
                ->setImageId($source->getImage()->getId())
                ->setUseSearchCache(!$withOutSearchCache);
            $this->bus->dispatch($message);

            // Free memory when batch size is reached.
            if (0 === ($i % $batchSize)) {
                $this->em->clear();
                gc_collect_cycles();
            }

            ++$i;
            $this->progressAdvance();
            $this->progressMessage('Source rows found '.($i - 1).' in DB');
        }

        $this->progressFinish();
        $output->writeln('');

        return Command::SUCCESS;
    }
}
