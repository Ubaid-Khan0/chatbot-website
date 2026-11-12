<?php

declare(strict_types=1);

namespace Nexum;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

final class Store
{
    private const LEAD_STATUSES = ['New', 'Contacted', 'Qualified', 'Won', 'Archived'];
    private const PLANS = ['Starter', 'Growth', 'Custom', 'Not sure'];
    private const ROLES = ['admin', 'editor', 'viewer'];

    public function dashboard(): array
    {
        $leads = Database::connection()->leads;
        $planMix = [];
        foreach (self::PLANS as $plan) {
            $planMix[$plan] = $leads->countDocuments(['plan' => $plan]);
        }
        return [
            'total' => $leads->countDocuments(),
            'followup' => $leads->countDocuments(['status' => ['$in' => ['New', 'Contacted']]]),
            'qualified' => $leads->countDocuments(['status' => 'Qualified']),
            'won' => $leads->countDocuments(['status' => 'Won']),
            'plan_mix' => $planMix,
            'recent' => $this->listLeads(['limit' => 5])['items'],
        ];
    }

    public function listLeads(array $filters = []): array
    {
        $query = [];
        if (!empty($filters['status']) && in_array($filters['status'], self::LEAD_STATUSES, true)) {
            $query['status'] = $filters['status'];
        }
        if (!empty($filters['plan']) && in_array($filters['plan'], self::PLANS, true)) {
            $query['plan'] = $filters['plan'];
        }
        if (!empty($filters['search'])) {
            $safe = preg_quote(substr((string) $filters['search'], 0, 100), '/');
            $query['$or'] = [
                ['name' => ['$regex' => $safe, '$options' => 'i']],
                ['company' => ['$regex' => $safe, '$options' => 'i']],
                ['email' => ['$regex' => $safe, '$options' => 'i']],
            ];
        }
        $limit = max(1, min((int) ($filters['limit'] ?? 100), 500));
        $cursor = Database::connection()->leads->find($query, ['sort' => ['created_at' => -1], 'limit' => $limit]);
        return ['items' => array_map([$this, 'normalize'], iterator_to_array($cursor, false)), 'total' => Database::connection()->leads->countDocuments($query)];
    }

    public function createLead(array $input): array
    {
        $recent = Database::connection()->leads->countDocuments([
            'source_ip' => Http::ip(),
            'created_at' => ['$gte' => new UTCDateTime((time() - 600) * 1000)],
        ]);
        if ($recent >= 5) {
            throw new HttpException(429, 'Too many enquiries were sent. Please wait a few minutes and try again.');
        }
        $lead = [
            'name' => $this->text($input, 'name', 2, 100),
            'company' => $this->text($input, 'company', 2, 120),
            'email' => Auth::normalizeEmail((string) ($input['email'] ?? '')),
            'plan' => in_array($input['plan'] ?? '', self::PLANS, true) ? $input['plan'] : 'Not sure',
            'message' => $this->text($input, 'message', 10, 3000),
            'status' => 'New',
            'notification_status' => 'pending',
            'source_ip' => Http::ip(),
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
        ];
        $result = Database::connection()->leads->insertOne($lead);
        $lead['_id'] = $result->getInsertedId();
        return $this->normalize($lead);
    }

    public function markLeadNotification(string $id, string $status, ?string $error = null): void
    {
        Database::connection()->leads->updateOne(['_id' => $this->id($id)], ['$set' => [
            'notification_status' => $status,
            'notification_error' => $error ? substr($error, 0, 500) : null,
            'updated_at' => new UTCDateTime(),
        ]]);
    }

    public function updateLead(string $id, array $input, array $actor): array
    {
        $status = (string) ($input['status'] ?? '');
        if (!in_array($status, self::LEAD_STATUSES, true)) {
            throw new HttpException(422, 'Invalid lead status.');
        }
        Database::connection()->leads->updateOne(['_id' => $this->id($id)], ['$set' => ['status' => $status, 'updated_at' => new UTCDateTime()]]);
        $this->audit($actor, 'lead.status_updated', 'lead', $id, ['status' => $status]);
        $lead = Database::connection()->leads->findOne(['_id' => $this->id($id)]);
        if (!$lead) {
            throw new HttpException(404, 'Lead not found.');
        }
        return $this->normalize($lead);
    }

    public function plans(bool $activeOnly = false): array
    {
        $query = $activeOnly ? ['active' => true] : [];
        return array_map([$this, 'normalize'], iterator_to_array(Database::connection()->plans->find($query, ['sort' => ['sort_order' => 1]]), false));
    }

    public function savePlans(array $plans, array $actor): array
    {
        foreach ($plans as $index => $plan) {
            $slug = strtolower(trim((string) ($plan['slug'] ?? '')));
            if (!preg_match('/^[a-z0-9-]{2,40}$/', $slug)) {
                throw new HttpException(422, 'Every plan requires a valid slug.');
            }
            Database::connection()->plans->updateOne(['slug' => $slug], ['$set' => [
                'slug' => $slug,
                'name' => $this->text($plan, 'name', 2, 60),
                'starting_price_pkr' => max(0, (int) ($plan['starting_price_pkr'] ?? 0)),
                'price_suffix' => substr((string) ($plan['price_suffix'] ?? ''), 0, 5),
                'description' => $this->text($plan, 'description', 10, 500),
                'features' => array_values(array_filter(array_map(fn ($value) => substr(trim((string) $value), 0, 150), (array) ($plan['features'] ?? [])))),
                'active' => (bool) ($plan['active'] ?? true),
                'sort_order' => (int) ($plan['sort_order'] ?? $index),
                'updated_at' => new UTCDateTime(),
            ], '$setOnInsert' => ['created_at' => new UTCDateTime()]], ['upsert' => true]);
        }
        $this->audit($actor, 'plans.updated', 'plans', null, ['count' => count($plans)]);
        return $this->plans();
    }

    public function content(): array
    {
        $items = iterator_to_array(Database::connection()->site_content->find(), false);
        $result = [];
        foreach ($items as $item) {
            $result[(string) $item['key']] = $item['value'];
        }
        return $result;
    }

    public function saveContent(array $content, array $actor): array
    {
        $allowed = ['heroTitle', 'heroLead', 'ctaTitle', 'contactEmail'];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $content)) {
                continue;
            }
            $value = $key === 'contactEmail' ? Auth::normalizeEmail((string) $content[$key]) : substr(trim((string) $content[$key]), 0, 500);
            Database::connection()->site_content->updateOne(['key' => $key], ['$set' => ['value' => $value, 'updated_at' => new UTCDateTime()]], ['upsert' => true]);
        }
        $this->audit($actor, 'content.updated', 'site_content', null, ['keys' => array_keys($content)]);
        return $this->content();
    }

    public function users(): array
    {
        $items = Database::connection()->users->find([], ['projection' => ['password_hash' => 0], 'sort' => ['created_at' => -1]]);
        return array_map([$this, 'normalize'], iterator_to_array($items, false));
    }

    public function createUser(array $input, array $actor): array
    {
        $password = (string) ($input['password'] ?? '');
        Auth::validatePassword($password);
        $role = (string) ($input['role'] ?? 'viewer');
        if (!in_array($role, self::ROLES, true)) {
            throw new HttpException(422, 'Invalid user role.');
        }
        $document = [
            'name' => $this->text($input, 'name', 2, 100),
            'email' => Auth::normalizeEmail((string) ($input['email'] ?? '')),
            'password_hash' => password_hash($password, Auth::passwordAlgorithm()),
            'role' => $role,
            'active' => true,
            'failed_attempts' => 0,
            'locked_until' => null,
            'created_at' => new UTCDateTime(),
            'updated_at' => new UTCDateTime(),
        ];
        try {
            $result = Database::connection()->users->insertOne($document);
        } catch (\MongoDB\Driver\Exception\BulkWriteException) {
            throw new HttpException(409, 'A user with this email already exists.');
        }
        $this->audit($actor, 'user.created', 'user', (string) $result->getInsertedId(), ['role' => $role]);
        unset($document['password_hash']);
        $document['_id'] = $result->getInsertedId();
        return $this->normalize($document);
    }

    public function updateUser(string $id, array $input, array $actor): array
    {
        if (($actor['id'] ?? null) === $id && (($input['active'] ?? true) === false || (isset($input['role']) && $input['role'] !== 'admin'))) {
            throw new HttpException(422, 'You cannot disable or remove the admin role from your own account.');
        }
        $updates = ['updated_at' => new UTCDateTime()];
        if (isset($input['role'])) {
            if (!in_array($input['role'], self::ROLES, true)) {
                throw new HttpException(422, 'Invalid user role.');
            }
            $updates['role'] = $input['role'];
        }
        if (array_key_exists('active', $input)) {
            $updates['active'] = (bool) $input['active'];
        }
        if (!empty($input['password'])) {
            Auth::validatePassword((string) $input['password']);
            $updates['password_hash'] = password_hash((string) $input['password'], Auth::passwordAlgorithm());
        }
        Database::connection()->users->updateOne(['_id' => $this->id($id)], ['$set' => $updates]);
        $this->audit($actor, 'user.updated', 'user', $id, ['fields' => array_keys($updates)]);
        $user = Database::connection()->users->findOne(['_id' => $this->id($id)], ['projection' => ['password_hash' => 0]]);
        if (!$user) {
            throw new HttpException(404, 'User not found.');
        }
        return $this->normalize($user);
    }

    public function audit(array $actor, string $action, string $entityType, ?string $entityId, array $metadata = []): void
    {
        Database::connection()->audit_logs->insertOne([
            'actor_id' => $actor['id'] ?? null,
            'actor_email' => $actor['email'] ?? null,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'ip' => Http::ip(),
            'created_at' => new UTCDateTime(),
        ]);
    }

    public function auditLog(int $limit = 100): array
    {
        $cursor = Database::connection()->audit_logs->find([], ['sort' => ['created_at' => -1], 'limit' => max(1, min($limit, 500))]);
        return array_map([$this, 'normalize'], iterator_to_array($cursor, false));
    }

    public function normalize(mixed $value): mixed
    {
        if ($value instanceof ObjectId) {
            return (string) $value;
        }
        if ($value instanceof UTCDateTime) {
            return $value->toDateTime()->format(DATE_ATOM);
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key === '_id' ? 'id' : $key] = $this->normalize($item);
            }
            return $result;
        }
        return $value;
    }

    private function id(string $value): ObjectId
    {
        try {
            return new ObjectId($value);
        } catch (\Throwable) {
            throw new HttpException(422, 'Invalid record identifier.');
        }
    }

    private function text(array $input, string $key, int $min, int $max): string
    {
        $value = trim((string) ($input[$key] ?? ''));
        $length = mb_strlen($value);
        if ($length < $min || $length > $max) {
            throw new HttpException(422, ucfirst(str_replace('_', ' ', $key)) . " must be between {$min} and {$max} characters.");
        }
        return $value;
    }
}
