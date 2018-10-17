<?php

namespace Drupal\Tests\content_moderation_notifications\Functional\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\content_moderation_notifications\Entity\ContentModerationNotification;
use Drupal\simpletest\ContentTypeCreationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\workflows\Entity\Workflow;

/**
 * Tests CRUD forms.
 *
 * @group content_moderation_notifications
 */
class CrudFormTest extends BrowserTestBase {

  use ContentTypeCreationTrait;

  /**
   * An admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'block',
    'content_moderation_notifications',
    'node',
    'filter_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->createContentType(['type' => 'article']);

    // Skip UID 1.
    $this->drupalCreateUser();

    // Admin user.
    $this->adminUser = $this->createUser([
      'administer content moderation notifications',
      'use text format filtered_html',
      'use text format full_html',
    ]);

    // Add local actions block.
    $this->placeBlock('local_actions_block');
  }

  /**
   * Test basic CRUD operations via the forms.
   */
  public function testCrud() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/workflow/notifications');
    $this->clickLink(t('Add notification'));

    // Test the add form.
    $edit = [
      'label' => $this->randomString(),
      'id' => Unicode::strtolower($this->randomMachineName()),
      'workflow' => 'editorial',
      'transitions[create_new_draft]' => TRUE,
      'transitions[archived_published]' => TRUE,
      'roles[authenticated]' => TRUE,
      'subject' => $this->randomString(),
      'body[value]' => $this->randomGenerator->paragraphs(2),
    ];
    $this->drupalPostForm(NULL, $edit, t('Create Notification'));

    /** @var \Drupal\content_moderation_notifications\ContentModerationNotificationInterface $notification */
    $notification = ContentModerationNotification::load($edit['id']);
    $this->assertSession()
      ->responseContains(t('Notification <a href=":url">%label</a> has been added.', ['%label' => $edit['label'], ':url' => $notification->toUrl('edit-form')->toString()]));

    $this->assertEquals($edit['id'], $notification->id());
    $this->assertEquals($edit['workflow'], $notification->getWorkflowId());
    $this->assertEquals(['authenticated' => 'authenticated'], $notification->getRoleIds());
    $this->assertEquals(['create_new_draft' => 'create_new_draft', 'archived_published' => 'archived_published'], $notification->getTransitions());

    // Test the edit form.
    $edit = [
      'subject' => $this->randomString(),
      'body[format]' => 'full_html',
      'body[value]' => $this->randomGenerator->paragraphs(3),
    ];
    $this->drupalGet($notification->toUrl('edit-form'));
    $this->drupalPostForm(NULL, $edit, t('Update Notification'));
    $this->assertSession()
      ->responseContains(t('Notification <a href=":url">%label</a> has been updated.', ['%label' => $notification->label(), ':url' => $notification->toUrl('edit-form')->toString()]));
    /** @var \Drupal\content_moderation_notifications\ContentModerationNotificationInterface $notification */
    $notification = ContentModerationNotification::load($notification->id());
    $this->assertEquals($edit['subject'], $notification->getSubject());
    $this->assertEquals($edit['body[value]'], $notification->getMessage());
    $this->assertEquals('full_html', $notification->getMessageFormat());

    // Test the disable form.
    $this->drupalGet($notification->toUrl('disable-form'));
    $this->drupalPostForm(NULL, [], t('Confirm'));
    /** @var \Drupal\content_moderation_notifications\ContentModerationNotificationInterface $notification */
    $notification = ContentModerationNotification::load($notification->id());
    $this->assertFalse($notification->status());

    // Verify appropriate links on collection page.
    $this->drupalGet($notification->toUrl('collection'));
    $this->assertSession()->linkExists(t('Enable'));

    // Test the enable form.
    $this->drupalGet($notification->toUrl('enable-form'));
    $this->drupalPostForm(NULL, [], t('Confirm'));
    /** @var \Drupal\content_moderation_notifications\ContentModerationNotificationInterface $notification */
    $notification = ContentModerationNotification::load($notification->id());
    $this->assertTrue($notification->status());

    // Verify appropriate links on collection page.
    $this->drupalGet($notification->toUrl('collection'));
    $this->assertSession()->linkExists(t('Disable'));

    // Test the delete form.
    $this->drupalGet($notification->toUrl('delete-form'));
    $this->drupalPostForm(NULL, [], t('Delete Notification'));
    $this->assertSession()->responseContains(t('Notification %label was deleted.', ['%label' => $notification->label()]));
    $this->assertSession()->pageTextContains(t('There is no Notification yet.'));
  }

  /**
   * Tests when no available workflows are in place.
   */
  public function testNoWorkflows() {
    // Remove the workflow.
    $workflow = Workflow::load('editorial');
    $workflow->delete();

    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/config/workflow/notifications');
    $this->clickLink(t('Add notification'));

    $this->assertSession()->pageTextContains(t('No workflows available.'));
    $this->assertSession()->linkExists(t('Manage workflows'));
    $this->assertSession()->buttonNotExists(t('Create Notification'));
  }

}
