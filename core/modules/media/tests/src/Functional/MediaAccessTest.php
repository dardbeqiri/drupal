<?php

namespace Drupal\Tests\media\Functional;

use Drupal\media\Entity\Media;
use Drupal\Tests\system\Functional\Cache\AssertPageCacheContextsAndTagsTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Basic access tests for Media.
 *
 * @group media
 */
class MediaAccessTest extends MediaUiFunctionalTest {

  use AssertPageCacheContextsAndTagsTrait;

  /**
   * Test some access control functionality.
   */
  public function testMediaAccess() {
    $assert_session = $this->assertSession();

    $media_type = $this->createMediaType();

    // Create media.
    $media = Media::create([
      'bundle' => $media_type->id(),
      'name' => 'Unnamed',
    ]);
    $media->save();
    $user_media = Media::create([
      'bundle' => $media_type->id(),
      'name' => 'Unnamed',
      'uid' => $this->nonAdminUser->id(),
    ]);
    $user_media->save();

    // We are logged in as admin, so test 'administer media' permission.
    $this->drupalGet('media/add/' . $media_type->id());
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(200);
    $this->drupalGet('media/' . $user_media->id());
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(200);
    $this->drupalGet('media/' . $user_media->id() . '/edit');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(200);
    $this->drupalGet('media/' . $user_media->id() . '/delete');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(200);

    $this->drupalLogin($this->nonAdminUser);
    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::load(RoleInterface::AUTHENTICATED_ID);

    // Test 'view media' permission.
    user_role_revoke_permissions($role->id(), ['view media']);
    $this->drupalGet('media/' . $media->id());
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);
    $access_result = $media->access('view', NULL, TRUE);
    $this->assertSame("The 'view media' permission is required and the media item must be published.", $access_result->getReason());
    $this->grantPermissions($role, ['view media']);
    $this->drupalGet('media/' . $media->id());
    $this->assertCacheContext('user');
    $assert_session->statusCodeEquals(200);

    // Test 'create media' permission.
    $this->drupalGet('media/add/' . $media_type->id());
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(403);
    $this->grantPermissions($role, ['create media']);
    $this->drupalGet('media/add/' . $media_type->id());
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(200);

    // Test 'update media' and 'delete media' permissions.
    $this->drupalGet('media/' . $user_media->id() . '/edit');
    $this->assertCacheContext('user');
    $assert_session->statusCodeEquals(403);
    $this->drupalGet('media/' . $user_media->id() . '/delete');
    $this->assertCacheContext('user');
    $assert_session->statusCodeEquals(403);
    $this->grantPermissions($role, ['update media']);
    $this->grantPermissions($role, ['delete media']);
    $this->drupalGet('media/' . $user_media->id() . '/edit');
    $this->assertCacheContext('user');
    $assert_session->statusCodeEquals(200);
    $this->drupalGet('media/' . $user_media->id() . '/delete');
    $this->assertCacheContext('user');
    $assert_session->statusCodeEquals(200);

    // Test 'update any media' and 'delete any media' permissions.
    $this->drupalGet('media/' . $media->id() . '/edit');
    $this->assertCacheContext('user');
    $assert_session->statusCodeEquals(403);
    $this->drupalGet('media/' . $media->id() . '/delete');
    $this->assertCacheContext('user');
    $assert_session->statusCodeEquals(403);
    $this->grantPermissions($role, ['update any media']);
    $this->grantPermissions($role, ['delete any media']);
    $this->drupalGet('media/' . $media->id() . '/edit');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(200);
    $this->drupalGet('media/' . $media->id() . '/delete');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(200);

    // Test the 'access media overview' permission.
    $this->grantPermissions($role, ['access content overview']);
    $this->drupalGet('admin/content');
    $assert_session->linkByHrefNotExists('/admin/content/media');
    $this->assertCacheContext('user');

    // Create a new role, which implicitly checks if the permission exists.
    $mediaOverviewRole = $this->createRole(['access content overview', 'access media overview']);
    $this->nonAdminUser->addRole($mediaOverviewRole);
    $this->nonAdminUser->save();

    $this->drupalGet('admin/content');
    $assert_session->linkByHrefExists('/admin/content/media');
    $this->clickLink('Media');
    $this->assertCacheContext('user.permissions');
    $assert_session->statusCodeEquals(200);
    $assert_session->elementExists('css', '.view-media');
    $assert_session->pageTextContains($this->loggedInUser->getDisplayName());
    $assert_session->pageTextContains($this->nonAdminUser->getDisplayName());
    $assert_session->linkByHrefExists('/media/' . $media->id());
    $assert_session->linkByHrefExists('/media/' . $user_media->id());
  }

}
