<?php

declare(strict_types=1);

namespace WPCBTPro\Tests\Integration\Candidates;

use WPCBTPro\Candidates\CandidateRepository;

final class CandidateRepositoryTest extends \WP_UnitTestCase
{
    private CandidateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CandidateRepository();
    }

    public function testInsertAndFindRoundTrip(): void
    {
        $institutionId = (int) get_option('wpcbtpro_default_institution_id');

        $id = $this->repository->insert([
            'institution_id' => $institutionId,
            'candidate_ref' => 'CBT-2026-000123',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@example.org',
            'status' => 'active',
        ]);

        $found = $this->repository->find($id);

        self::assertNotNull($found);
        self::assertSame('Ada', $found['first_name']);
        self::assertSame('CBT-2026-000123', $found['candidate_ref']);
        self::assertSame($this->repository->findByRef('CBT-2026-000123')['id'] ?? null, $found['id']);
    }

    public function testFindByWpUserIdReturnsNullWhenUnlinked(): void
    {
        self::assertNull($this->repository->findByWpUserId(999999));
    }

    public function testCandidateRefMustBeUnique(): void
    {
        $institutionId = (int) get_option('wpcbtpro_default_institution_id');

        $this->repository->insert([
            'institution_id' => $institutionId,
            'candidate_ref' => 'CBT-2026-000456',
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'status' => 'active',
        ]);

        global $wpdb;
        $suppressed = $wpdb->suppress_errors(true);

        $secondId = $this->repository->insert([
            'institution_id' => $institutionId,
            'candidate_ref' => 'CBT-2026-000456',
            'first_name' => 'Duplicate',
            'last_name' => 'Ref',
            'status' => 'active',
        ]);

        $wpdb->suppress_errors($suppressed);

        self::assertSame(0, $secondId, 'A duplicate candidate_ref should fail the unique-key insert.');
    }
}
