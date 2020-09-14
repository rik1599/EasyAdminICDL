<?php

namespace App\Controller\Admin;

use App\Entity\Student;
use App\Entity\User;
use App\Field\PasswordField;
use App\Service\UserSecurityService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\CrudUrlGenerator;

class UserCrudController extends AbstractCrudController
{
    /** @var UserSecurityService */
    private $userSecurityService;

    public function __construct(UserSecurityService $userSecurityService)
    {
        $this->userSecurityService = $userSecurityService;
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsAsDropdown(true);
    }

    public function configureActions(Actions $actions): Actions
    {
        $changeBirth = Action::new('changeBirth', 'Modifica la data di nascita', 'fa fa-birthday-cake')
            ->displayIf(static function (User $user) {
                return !is_null($user->getStudent());
            })
            ->linkToUrl(function(User $user) {
                /** @var CrudUrlGenerator */
                $crudUrlGenerator = $this->get(CrudUrlGenerator::class);
                $url = $crudUrlGenerator->build()
                    ->setController(StudentCrudController::class)
                    ->setAction(Action::EDIT)
                    ->setEntityId($user->getStudent()->getId())
                    ->generateUrl();
                return $url;
            });
        
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_EDIT, $changeBirth)
            ->add(Crud::PAGE_INDEX, $changeBirth);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('firstName', 'Nome');
        yield TextField::new('lastName', 'Cognome');
        yield EmailField::new('email');
        yield PasswordField::new('password', 'Password')->onlyWhenCreating();
        yield ChoiceField::new('role', 'Permessi')->setChoices(User::ROLES)->setRequired(true)->onlyWhenCreating();
        yield DateTimeField::new('createdAt')->hideOnForm();
        yield DateTimeField::new('updatedAt')->hideOnForm();
        yield DateTimeField::new('lastLoginAt')->hideOnForm();
        yield AssociationField::new('student', 'Data di nascita')
            ->formatValue(function ($value, User $entity) {
                return $entity->getStudent()->getBirthDate()->format('d/m/Y');
            })
            ->addCssClass('btn-link disabled')
            ->onlyOnDetail();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var User $entityInstance */
        $this->userSecurityService->setupUserPassword($entityInstance, $entityInstance->getPassword());
        $entityInstance->setCreatedAt(new \DateTime());
        parent::persistEntity($entityManager, $entityInstance);
        
        if (in_array('ROLE_STUDENT', $entityInstance->getRoles())) {
            $student = new Student();
            $student->setUser($entityInstance);
            $student->setBirthDate(new \DateTime('1990-01-01'));
            $entityManager->persist($student);
            $entityManager->flush();
        }
    }
}
