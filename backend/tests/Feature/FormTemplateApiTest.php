<?php

namespace Tests\Feature;

use App\Models\FormTemplate;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FormTemplateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_create_update_archive_and_list_form_templates(): void
    {
        $admin = $this->createRoleUser('admin');
        Sanctum::actingAs($admin);

        $createResponse = $this->postJson('/api/v1/admin/form-templates', [
            'title' => 'Student Absence Notice',
            'description' => 'Used when a student cannot attend class.',
            'category' => 'student absence form',
            'fields' => [
                [
                    'name' => 'absence_date',
                    'label' => 'Absence date',
                    'type' => 'date',
                    'required' => true,
                ],
                [
                    'name' => 'reason',
                    'label' => 'Reason',
                    'type' => 'select',
                    'required' => true,
                    'options' => ['Sick', 'Family emergency'],
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Student Absence Notice')
            ->assertJsonPath('data.category', FormTemplate::TYPE_STUDENT_ABSENCE_FORM)
            ->assertJsonPath('data.status', FormTemplate::STATUS_ACTIVE)
            ->assertJsonPath('data.created_by', $admin->id);

        $templateId = $createResponse->json('data.id');

        $this->patchJson("/api/v1/admin/form-templates/{$templateId}", [
            'title' => 'Updated Student Absence Notice',
            'schema' => [
                'fields' => [
                    [
                        'name' => 'details',
                        'label' => 'Details',
                        'type' => 'textarea',
                        'required' => true,
                    ],
                ],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Student Absence Notice')
            ->assertJsonPath('data.version', 2)
            ->assertJsonPath('data.updated_by', $admin->id);

        $this->postJson("/api/v1/admin/form-templates/{$templateId}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', FormTemplate::STATUS_ARCHIVED)
            ->assertJsonPath('data.is_archived', true);

        $this->getJson('/api/v1/admin/form-templates?only_archived=true')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $templateId);

        $this->assertDatabaseHas('form_templates', [
            'id' => FormTemplate::where('public_id', $templateId)->value('id'),
            'name' => 'Updated Student Absence Notice',
            'template_type' => FormTemplate::TYPE_STUDENT_ABSENCE_FORM,
            'status' => FormTemplate::STATUS_ARCHIVED,
            'version' => 2,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }

    public function test_schema_is_validated_before_saving(): void
    {
        Sanctum::actingAs($this->createRoleUser('admin'));

        $this->postJson('/api/v1/admin/form-templates', [
            'title' => 'Material Request',
            'category' => FormTemplate::TYPE_MATERIAL_REQUEST_FORM,
            'schema' => [
                'fields' => [
                    [
                        'name' => 'material type',
                        'label' => 'Material type',
                        'type' => 'select',
                    ],
                ],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'schema.fields.0.name',
                'schema.fields.0.options',
            ]);

        $this->assertDatabaseCount('form_templates', 0);
    }

    public function test_non_admin_users_cannot_modify_form_templates(): void
    {
        Sanctum::actingAs($this->createRoleUser('student'));

        $this->postJson('/api/v1/admin/form-templates', [
            'title' => 'Class Incident',
            'category' => FormTemplate::TYPE_CLASS_INCIDENT_FORM,
            'fields' => [
                [
                    'name' => 'details',
                    'label' => 'Details',
                    'type' => 'textarea',
                    'required' => true,
                ],
            ],
        ])->assertForbidden();
    }

    public function test_staff_form_template_crud_depends_on_view_and_manage_permissions(): void
    {
        $staff = $this->createRoleUser('staff');
        $template = FormTemplate::factory()->create([
            'name' => 'Class Incident',
            'template_type' => FormTemplate::TYPE_CLASS_INCIDENT_FORM,
            'status' => FormTemplate::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/admin/form-templates')->assertForbidden();
        $this->postJson('/api/v1/admin/form-templates', [
            'title' => 'Material Request',
            'category' => FormTemplate::TYPE_MATERIAL_REQUEST_FORM,
            'fields' => [
                [
                    'name' => 'request_details',
                    'label' => 'Request details',
                    'type' => 'textarea',
                    'required' => true,
                ],
            ],
        ])->assertForbidden();

        $staff->givePermissionTo('form_templates.view');

        $this->getJson('/api/v1/admin/form-templates')
            ->assertOk()
            ->assertJsonFragment(['id' => $template->public_id]);
        $this->getJson("/api/v1/admin/form-templates/{$template->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $template->public_id);
        $this->patchJson("/api/v1/admin/form-templates/{$template->public_id}", [
            'title' => 'Updated Class Incident',
        ])->assertForbidden();

        $staff->givePermissionTo('form_templates.manage');

        $createResponse = $this->postJson('/api/v1/admin/form-templates', [
            'title' => 'Material Request',
            'category' => FormTemplate::TYPE_MATERIAL_REQUEST_FORM,
            'fields' => [
                [
                    'name' => 'request_details',
                    'label' => 'Request details',
                    'type' => 'textarea',
                    'required' => true,
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.created_by', $staff->id);

        $createdTemplateId = $createResponse->json('data.id');

        $this->patchJson("/api/v1/admin/form-templates/{$createdTemplateId}", [
            'title' => 'Updated Material Request',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated Material Request')
            ->assertJsonPath('data.updated_by', $staff->id);

        $this->postJson("/api/v1/admin/form-templates/{$createdTemplateId}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', FormTemplate::STATUS_ARCHIVED);
    }

    public function test_users_only_retrieve_active_forms_allowed_for_their_role(): void
    {
        $studentForm = FormTemplate::factory()->create([
            'name' => 'Student Absence',
            'template_type' => FormTemplate::TYPE_STUDENT_ABSENCE_FORM,
            'status' => FormTemplate::STATUS_ACTIVE,
        ]);
        $teacherForm = FormTemplate::factory()->create([
            'name' => 'Teacher Absence',
            'template_type' => FormTemplate::TYPE_TEACHER_ABSENCE_FORM,
            'status' => FormTemplate::STATUS_ACTIVE,
        ]);
        FormTemplate::factory()->create([
            'name' => 'Archived Student Absence',
            'template_type' => FormTemplate::TYPE_STUDENT_ABSENCE_FORM,
            'status' => FormTemplate::STATUS_ARCHIVED,
        ]);

        Sanctum::actingAs($this->createRoleUser('student'));

        $this->getJson('/api/v1/form-templates')
            ->assertOk()
            ->assertJsonFragment(['id' => $studentForm->public_id])
            ->assertJsonMissing(['id' => $teacherForm->public_id])
            ->assertJsonMissing(['title' => 'Archived Student Absence']);

        $this->getJson("/api/v1/form-templates/{$teacherForm->public_id}")
            ->assertForbidden();

        Sanctum::actingAs($this->createRoleUser('teacher'));

        $this->getJson("/api/v1/form-templates/{$teacherForm->public_id}")
            ->assertOk()
            ->assertJsonPath('data.id', $teacherForm->public_id);
    }

    private function createRoleUser(string $role): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        return $user;
    }
}
