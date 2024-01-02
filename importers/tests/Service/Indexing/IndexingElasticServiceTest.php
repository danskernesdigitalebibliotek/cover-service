<?php

namespace App\Tests\Service\Indexing;

use App\Exception\SearchIndexException;
use App\Service\Indexing\IndexingElasticService;
use App\Service\Indexing\IndexItem;
use OpenSearch\Client;
use OpenSearch\Common\Exceptions\ClientErrorResponseException;
use OpenSearch\Common\Exceptions\ServerErrorResponseException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Response;

class IndexingElasticServiceTest extends KernelTestCase
{
    private IndexingElasticService $indexingElasticService;
    private Client $client;
    private string $indexName;
    private string $indexAliasName;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $container = static::getContainer();

        $this->indexingElasticService = $container->get(IndexingElasticService::class);
        /** @var Client $client */
        $client = $container->get(Client::class);
        $this->client = $client;
        $this->indexAliasName = $_ENV['INDEXING_ALIAS'];
        $this->indexName = $this->indexAliasName.'_'.date('Y-m-d-His');

        $class = new \ReflectionClass(IndexingElasticService::class);
        $method = $class->getMethod('createIndex');
        $method->setAccessible(true);

        $method->invokeArgs($this->indexingElasticService, [$this->indexName]);
        $this->client->indices()->updateAliases([
            'body' => [
                'actions' => [
                    [
                        'add' => [
                            'index' => $this->indexName,
                            'alias' => $this->indexAliasName,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        try {
            if ($this->client->indices()->exists(['index' => $this->indexName])) {
                $this->client->indices()->delete(['index' => $this->indexName]);
            }
        } catch (ClientErrorResponseException|ServerErrorResponseException $e) {
            $this->fail('Unexpected exception: '.\get_class($e).', '.$e->getMessage());
        }
    }

    public function testIndex(): void
    {
        $item = $this->getIndexItemFixture();

        try {
            // Test new item can be indexed to ES
            $this->indexingElasticService->index($item);
            $this->assertItemIndexed($item);

            // Test updated item can be indexed to ES
            $item->setImageUrl('https://test.com/test2.jpg');
            $this->indexingElasticService->index($item);
            $this->assertItemIndexed($item);
        } catch (SearchIndexException $e) {
            $this->fail('Unexpected SearchIndexException thrown for index operation');
        }
    }

    public function testDelete(): void
    {
        $item = $this->getIndexItemFixture();

        try {
            // Test new item can be indexed to ES
            $this->indexingElasticService->index($item);
            $this->assertItemIndexed($item);

            // Test delete
            $this->indexingElasticService->delete($item->getId());

            // Verify delete
            $this->expectException(ClientResponseException::class);
            $this->expectExceptionMessage('404 Not Found: {"_index":"'.$this->indexName.'","_id":"1","found":false}');

            $response = $this->client->get([
                'index' => $this->indexAliasName,
                'id' => $item->getId(),
            ]);
        } catch (SearchIndexException $e) {
            $this->fail('Unexpected SearchIndexException thrown for index operation');
        }
    }

    /**
     * Assert that ES data match given IndexItem.
     *
     * @param IndexItem $item
     *
     * @return void
     */
    private function assertItemIndexed(IndexItem $item): void
    {
        try {
            $this->client->indices()->refresh(['index' => $this->indexAliasName]);

            $response = $this->client->get([
                'index' => $this->indexAliasName,
                'id' => $item->getId(),
            ]);

            $source = $response['_source'];

            $this->assertEquals(
                $item->getHeight(),
                $source['height'],
                sprintf('IndexItem:height %d does not match OS data:height %d', $item->getHeight(), $source['height'])
            );
            $this->assertEquals(
                $item->getWidth(),
                $source['width'],
                sprintf('IndexItem:width %d does not match OS data:width %d', $item->getWidth(), $source['width'])
            );
            $this->assertEquals(
                $item->getImageFormat(),
                $source['imageFormat'],
                sprintf(
                    'IndexItem:imageFormat %s does not match OS data:imageFormat %s',
                    $item->getImageFormat(),
                    $source['imageFormat']
                )
            );
            $this->assertEquals(
                $item->getImageUrl(),
                $source['imageUrl'],
                sprintf(
                    'IndexItem:imageUrl %s does not match OS data:imageUrl %s',
                    $item->getImageUrl(),
                    $source['imageUrl']
                )
            );
            $this->assertEquals(
                $item->getIsIdentifier(),
                $source['isIdentifier'],
                sprintf(
                    'IndexItem:isIdentifier %s does not match OS data:isIdentifier %s',
                    $item->getIsIdentifier(),
                    $source['isIdentifier']
                )
            );
            $this->assertEquals(
                $item->getIsType(),
                $source['isType'],
                sprintf('IndexItem:isType %s does not match OS data:isType %s', $item->getIsType(), $source['isType'])
            );
        } catch (ClientErrorResponseException|ServerErrorResponseException $e) {
            $this->fail('Unexpected exception: '.\get_class($e).', '.$e->getMessage());
        }
    }

    /**
     * Get fixture item.
     *
     * @return IndexItem
     */
    private function getIndexItemFixture(): IndexItem
    {
        $item = new IndexItem();
        $item->setId(1);
        $item->setHeight(400);
        $item->setWidth(300);
        $item->setImageFormat('jpg');
        $item->setImageUrl('https://test.com/test1.jpg');
        $item->setIsIdentifier('12345678');
        $item->setIsType('faust');
        $item->setGenericCover('false');

        return $item;
    }
}
