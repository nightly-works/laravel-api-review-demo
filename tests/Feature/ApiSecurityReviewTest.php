<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One test per finding of docs/security-review.md, sent with real Sanctum tokens as the mobile app
 * or an integration would send them. A test passes only when the attack is refused and normal use
 * still works. Against the app as delivered (tag as-delivered) every numbered test fails:
 * scripts/prove-flaws.sh.
 */
class ApiSecurityReviewTest extends TestCase
{
    use RefreshDatabase;

    private function call_(string $method, string $uri, ?string $token, array $data = [])
    {
        // Each request authenticates from its own token, as separate HTTP requests would.
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withHeaders($token ? ['Authorization' => 'Bearer '.$token] : [])->json($method, $uri, $data);
    }

    private function tokenFor(User $user, array $abilities = ['*']): string
    {
        return $user->createToken('test', $abilities)->plainTextToken;
    }

    public function test_1_user_search_returns_names_only(): void
    {
        $alice = User::factory()->create(['name' => 'Alice']);
        User::factory()->create(['name' => 'Bob Martin', 'email' => 'bob@example.test', 'phone' => '+33600000000']);

        $res = $this->call_('GET', '/api/users/search?q=Bob', $this->tokenFor($alice))->assertOk();

        $res->assertJsonPath('0.name', 'Bob Martin');
        $this->assertStringNotContainsString('bob@example.test', $res->getContent());
        $this->assertStringNotContainsString('+33600000000', $res->getContent());
        $this->assertStringNotContainsString('is_admin', $res->getContent());
    }

    public function test_2_read_only_tokens_cannot_change_anything(): void
    {
        $alice = User::factory()->create();
        $project = Project::factory()->for($alice)->create();
        $task = Task::factory()->for($project)->create(['done' => false]);
        $full = $this->tokenFor($alice);

        $readOnly = $this->call_('POST', '/api/tokens/read-only', $full, ['name' => 'calendar sync'])->json('token');

        $this->call_('GET', '/api/projects', $readOnly)->assertOk();
        $this->call_('PATCH', "/api/projects/{$project->id}/tasks/{$task->id}", $readOnly, ['done' => true])->assertForbidden();
        $this->call_('DELETE', "/api/projects/{$project->id}", $readOnly)->assertForbidden();
        $this->assertModelExists($project);
        $this->assertFalse($task->fresh()->done);

        $this->call_('DELETE', "/api/projects/{$project->id}", $full)->assertNoContent();
        $this->assertModelMissing($project);
    }

    public function test_3_tasks_are_reached_only_through_their_own_project(): void
    {
        $alice = User::factory()->create();
        $aliceProject = Project::factory()->for($alice)->create();
        $aliceTask = Task::factory()->for($aliceProject)->create();
        $bobTask = Task::factory()->create(['title' => 'Bob private task']);
        $token = $this->tokenFor($alice);

        // Alice's own project in the URL, Bob's task id after it.
        $this->call_('GET', "/api/projects/{$aliceProject->id}/tasks/{$bobTask->id}", $token)->assertNotFound();
        $this->call_('PATCH', "/api/projects/{$aliceProject->id}/tasks/{$bobTask->id}", $token, ['title' => 'renamed'])->assertNotFound();
        $this->assertSame('Bob private task', $bobTask->fresh()->title);

        $this->call_('GET', "/api/projects/{$aliceProject->id}/tasks/{$aliceTask->id}", $token)->assertOk();
    }

    public function test_4_changing_the_password_revokes_the_other_tokens(): void
    {
        $alice = User::factory()->create(['password' => 'old-password-123']);
        $stolen = $this->tokenFor($alice);
        $current = $this->tokenFor($alice);

        $this->call_('PUT', '/api/user/password', $current, [
            'current_password' => 'old-password-123',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ])->assertNoContent();

        $this->call_('GET', '/api/projects', $stolen)->assertUnauthorized();
        $this->call_('GET', '/api/projects', $current)->assertOk();
    }

    public function test_5_owners_still_use_the_api_normally(): void
    {
        $alice = User::factory()->create();
        $project = Project::factory()->for($alice)->create();
        $task = Task::factory()->for($project)->create();
        $token = $this->tokenFor($alice);

        $this->call_('GET', '/api/projects', $token)->assertOk()->assertJsonPath('0.id', $project->id);
        $this->call_('PATCH', "/api/projects/{$project->id}/tasks/{$task->id}", $token, ['done' => true])->assertOk();
        $this->assertTrue($task->fresh()->done);
        $this->call_('GET', '/api/projects', null)->assertUnauthorized();
    }
}
