<?php

namespace Bsll\ApiBundle\Tests\Controller;

use Bsll\ApiBundle\Tests\Entity\TestEntity;
use Bsll\ApiBundle\Tests\Form\TestEntityType;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Bsll\ApiBundle\Controller\BaseController;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormFactory;

class BaseControllerTest extends TestCase
{
    /**
     * @var BaseController
     */
    protected $testController;

    /**
     * @var MockObject
     */
    protected $containerMock;

    /**
     * @var MockObject
     */
    protected $formBuilderMock;

    /**
     * @var MockObject
     */
    protected $formFactoryMock;

    /**
     * @var TestEntity
     */
    protected $testEntity;

    /**
     * @var MockObject
     */
    protected $objectRepositoryMock;

    /**
     * @var MockObject
     */
    protected $entityManagerMock;

    /**
     * @var MockObject
     */
    protected $requestExistingEntity;

    /**
     * @var MockObject
     */
    protected $requestNotExistingEntity;

    /**
     * @return TestEntity
     */
    protected function getTestEntity(): TestEntity
    {
        if (!$this->testEntity) {
            $entity = new TestEntity();
            $entity->setId(1);
            $entity->setName('test');
            $this->testEntity = $entity;
        }

        return $this->testEntity;
    }

    /**
     * @return MockObject
     */
    protected function getObjectRepositoryMock(): MockObject
    {
        if (!$this->objectRepositoryMock) {
            $entity = $this->getTestEntity();

            $objectRepositoryMock = $this->createMock(ObjectRepository::class);
            $objectRepositoryMock->expects($this->any())
                ->method('findAll')
                ->willReturn([$entity]);

            $this->objectRepositoryMock = $objectRepositoryMock;
        }

        return $this->objectRepositoryMock;
    }

    /**
     * @return MockObject
     */
    protected function getEntityManagerMock(): MockObject
    {
        if (!$this->entityManagerMock) {
            $entityManagerMock = $this->createMock(EntityManager::class);
            $entityManagerMock->expects($this->any())
                ->method('getRepository')
                ->with(TestEntity::class)
                ->willReturn($this->getObjectRepositoryMock());

            $entityManagerMock->expects($this->any())
                ->method('find')
                ->will($this->returnCallback(function($entityClass, $id) {
                    $entity = $this->getTestEntity();
                    if ($id === $entity->getId()) {
                        return $entity;
                    } else {
                        return null;
                    }
                }));

            $this->entityManagerMock = $entityManagerMock;
        }

        return $this->entityManagerMock;
    }

    /**
     * @return MockObject
     */
    protected function getFormFactoryMock(): MockObject
    {
        if (!$this->formFactoryMock) {
            $formFactoryMock = $this->createMock(FormFactory::class);
            $formFactoryMock->expects($this->any())
                ->method('createNamed')
                ->willReturn($this->getFormBuilderMock());

            $this->formFactoryMock = $formFactoryMock;
        }

        return $this->formFactoryMock;
    }

    /**
     * @return MockObject
     */
    protected function getFormBuilderMock(): MockObject
    {
        if (!$this->formBuilderMock) {
            $formBuilderMock = $this->createMock(Form::class);
            $formBuilderMock->expects($this->any())
                ->method('getData')
                ->willReturn($this->getTestEntity());

            $formBuilderMock->expects($this->any())
                ->method('handleRequest')
                ->willReturn(true);

            $formBuilderMock->expects($this->any())
                ->method('isSubmitted')
                ->willReturn(true);

            $formBuilderMock->expects($this->any())
                ->method('isValid')
                ->willReturn(true);

            $this->formBuilderMock = $formBuilderMock;
        }

        return $this->formBuilderMock;
    }

    /**
     * @return MockObject
     */
    protected function getContainerMock(): MockObject
    {
        if (!$this->containerMock) {
            $containerMock = $this->createMock(Container::class);

            $containerMock->expects($this->any())
                ->method('get')
                ->with('form.factory')
                ->willReturn($this->getFormFactoryMock());

            $this->containerMock = $containerMock;
        }

        return $this->containerMock;
    }

    /**
     * @return BaseController
     */
    protected function getTestController(): BaseController
    {
        if (!$this->testController) {
            $testClass = new class(
                $this->getEntityManagerMock(),
                TestEntity::class,
                TestEntityType::class
            ) extends BaseController {

            };

            $testController = new $testClass(
                $this->getEntityManagerMock(),
                TestEntity::class,
                TestEntityType::class
            );

            $testController->setContainer($this->getContainerMock());

            $this->testController = $testController;
        }

        return $this->testController;
    }

    /**
     * @return MockObject
     */
    public function getRequestExistingEntity(): MockObject
    {
        if (!$this->requestExistingEntity) {
            $request = $this->createMock(Request::class);
            $request->expects($this->any())
                ->method('get')
                ->with('id')
                ->willReturn(1);

            $this->requestExistingEntity = $request;
        }

        return $this->requestExistingEntity;
    }

    /**
     * @return MockObject
     */
    public function getRequestNotExistingEntity(): MockObject
    {
        if (!$this->requestNotExistingEntity) {
            $request = $this->createMock(Request::class);
            $request->expects($this->any())
                ->method('get')
                ->with('id')
                ->willReturn(999);

            $this->requestNotExistingEntity = $request;
        }

        return $this->requestNotExistingEntity;
    }

    public function testListAction(): void
    {
        $entity = $this->getTestEntity();
        $controller = $this->getTestController();
        $request = $this->createMock(Request::class);
        $this->assertEquals([$entity], $controller->listAction($request));
    }

    public function testGetActionWithExistingEntity(): void
    {
        $entity = $this->getTestEntity();
        $controller = $this->getTestController();

        $this->assertEquals($entity, $controller->getAction($this->getRequestExistingEntity()));
    }

    public function testGetActionWithNotExistingEntity(): void
    {
        $controller = $this->getTestController();
        $this->expectException(NotFoundHttpException::class);
        $controller->getAction($this->getRequestNotExistingEntity());
    }

    public function testAddAction(): void
    {
        $entity = $this->getTestEntity();
        $controller = $this->getTestController();

        $this->assertEquals($entity, $controller->addAction($this->getRequestExistingEntity()));
    }

    public function testEditActionWithExistingEntity(): void
    {
        $entity = $this->getTestEntity();
        $controller = $this->getTestController();

        $this->assertEquals($entity, $controller->editAction($this->getRequestExistingEntity()));
    }

    public function testEditActionWithNotExistingEntity(): void
    {
        $controller = $this->getTestController();
        $this->expectException(NotFoundHttpException::class);
        $controller->editAction($this->getRequestNotExistingEntity());
    }

    public function testDeleteActionWithExistingEntity(): void
    {
        $entity = $this->getTestEntity();
        $controller = $this->getTestController();

        $this->assertEquals($entity, $controller->deleteAction($this->getRequestExistingEntity()));
    }

    public function testDeleteActionWithNotExistingEntity(): void
    {
        $controller = $this->getTestController();

        $this->expectException(NotFoundHttpException::class);
        $controller->deleteAction($this->getRequestNotExistingEntity());
    }
}