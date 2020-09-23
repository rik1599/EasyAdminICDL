<?php

namespace App\Controller\Admin;

use App\Entity\CertificationModule;
use App\Entity\SkillCard;
use App\Entity\SkillCardModule;
use App\Enum\EnumSkillCard;
use App\Form\SkillCardModuleType;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use InvalidArgumentException;

class SkillCardCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return SkillCard::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $update = Action::new('Rinnova')
            ->displayIf(static function (SkillCard $skillCard) {
                return !is_null($skillCard->getCertification()->getUpdateCertification());
            })
            ->linkToCrudAction('renovateSkillCard');

        return $actions
            ->add(Crud::PAGE_INDEX, $update)
            ->add(Crud::PAGE_DETAIL, $update)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('number', 'Numero');
        yield AssociationField::new('student', 'Email studente');
        yield AssociationField::new('certification', 'Certificazione');
        yield IntegerField::new('credits', 'Crediti');
        yield DateField::new('expiresAt', 'Data scadenza')
            ->setHelp('Se non inserito verrà eventualmente usata la data odierna più la durata del certificazione scelta');
        yield TextField::new('status')
            ->hideOnForm();

        if ($pageName === Crud::PAGE_EDIT) {
            $adminContext = $this->get(AdminContextProvider::class)->getContext();

            /** @var SkillCard $skillCard */
            $skillCard = $adminContext->getEntity()->getInstance();
            yield CollectionField::new('skillCardModules', 'Esami')
                ->setFormTypeOptions([
                    'entry_type' => SkillCardModuleType::class,
                    'by_reference' => false,
                    'allow_delete' => true,
                    'entry_options' => [
                        'certification' => $skillCard->getCertification(),
                        'label' => false,
                    ],
                    'row_attr' => ['class' => 'form-inline']
                ]);
        }
    }

    /**
     * @param EntityManagerInterface $entityManager
     * @param SkillCard $entityInstance
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance->getCertification()->hasExpiry() && is_null($entityInstance->getExpiresAt())) {
            $entityInstance->setExpiresAt(
                (new DateTime())->add($entityInstance->getCertification()->getDuration())
            );
        }

        $this->addMandatoryModules($entityInstance);

        $entityInstance->setStatus(EnumSkillCard::ACTIVATED);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function renovateSkillCard(AdminContext $adminContext)
    {
        /** @var SkillCard */
        $skillCard = $adminContext->getEntity()->getInstance();
        $updateCert = $skillCard->getCertification()->getUpdateCertification();
        $skillCard->setCertification($updateCert);
        switch ($skillCard->getStatus()) {
            case EnumSkillCard::ACTIVATED:
                $skillCard->setStatus(EnumSkillCard::UPDATING);
                $this->addMandatoryModules($skillCard);
                break;
            
            case EnumSkillCard::UPDATING:
                $skillCard->setStatus(EnumSkillCard::ACTIVATED);
                break;

            default:
                throw new InvalidArgumentException("Stato SkillCard non valido");
        }


        /** @var DateTime */
        $oldExpiry = clone ($skillCard->getExpiresAt());
        if (!is_null($updateCert->getDuration()) && $oldExpiry instanceof DateTime) {
            $newExpiry = clone ($oldExpiry->add($updateCert->getDuration()));
            $skillCard->setExpiresAt($newExpiry);
        }
        //$this->getDoctrine()->getManager()->flush();
        //return $this->redirect($adminContext->getReferrer());
    }

    protected function addMandatoryModules(SkillCard $skillCard)
    {
        /*
         * TODO: in fase di rinnovo aggiungere gli esami della nuova certificazione (se non sono già presenti nel portfolio)
         * Se questi sono già presenti nel portfolio, allora impostare il campo isPassed come false
        */
        $cmRepo = $this->getDoctrine()->getRepository(CertificationModule::class);
        $mandatoryModules = $cmRepo->findByCertification($skillCard->getCertification(), true);
        $modules = $skillCard->getSkillCardModules()->getValues();

        foreach ($mandatoryModules as $module) {
            $skillCard->addSkillCardModule(
                (new SkillCardModule())
                    ->setModule($module)
                    ->setIsPassed(false)
            );
        }
    }
}
