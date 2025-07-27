<?php

declare(strict_types=1);

namespace LinkShortener\Controllers;

use LinkShortener\Models\Partner;
use LinkShortener\Models\Advertisement;
use LinkShortener\Models\SubscriptionPlan;
use LinkShortener\Services\AdminService;
use LinkShortener\Services\PartnerService;
use LinkShortener\Services\AdvertisementService;
use LinkShortener\Services\AnalyticsService;
use LinkShortener\Services\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Twig\Environment;

class AdminController
{
    public function __construct(
        private AdminService $adminService,
        private PartnerService $partnerService,
        private AdvertisementService $advertisementService,
        private AnalyticsService $analyticsService,
        private SystemConfigService $configService,
        private Environment $twig
    ) {}

    // ============================================================================
    // ADMIN DASHBOARD
    // ============================================================================

    public function dashboard(Request $request): Response
    {
        $this->requireAdminAuth($request);
        
        $dashboardData = $this->adminService->getDashboardData();
        
        return new Response($this->twig->render('admin/dashboard.html.twig', [
            'data' => $dashboardData,
            'title' => 'Admin Dashboard'
        ]));
    }

    // ============================================================================
    // PARTNER MANAGEMENT
    // ============================================================================

    public function partnersIndex(Request $request): Response
    {
        $this->requireAdminAuth($request);
        
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 50);
        $status = $request->query->get('status', 'all');
        $search = $request->query->get('search', '');
        
        $partners = $this->partnerService->getPartnersForAdmin([
            'page' => $page,
            'limit' => $limit,
            'status' => $status,
            'search' => $search
        ]);
        
        return new Response($this->twig->render('admin/partners/index.html.twig', [
            'partners' => $partners,
            'filters' => compact('status', 'search'),
            'title' => 'Partner Management'
        ]));
    }

    public function partnerDetails(Request $request, string $partnerId): Response
    {
        $this->requireAdminAuth($request);
        
        $partner = $this->partnerService->getPartnerById($partnerId);
        if (!$partner) {
            throw new \Exception('Partner not found', 404);
        }
        
        $analytics = $this->analyticsService->getPartnerAnalytics($partnerId, [
            'period' => '30d',
            'detailed' => true
        ]);
        
        $recentActivity = $this->adminService->getPartnerActivity($partnerId);
        
        return new Response($this->twig->render('admin/partners/details.html.twig', [
            'partner' => $partner,
            'analytics' => $analytics,
            'activity' => $recentActivity,
            'title' => "Partner: {$partner->getCompanyName()}"
        ]));
    }

    public function updatePartnerStatus(Request $request, string $partnerId): JsonResponse
    {
        $this->requireAdminAuth($request);
        
        $data = json_decode($request->getContent(), true);
        $status = $data['status'] ?? '';
        $reason = $data['reason'] ?? '';
        
        $partner = $this->adminService->updatePartnerStatus($partnerId, $status, $reason);
        
        return new JsonResponse([
            'success' => true,
            'partner' => $partner->toArray(),
            'message' => "Partner status updated to {$status}"
        ]);
    }

    public function updatePartnerPermissions(Request $request, string $partnerId): JsonResponse
    {
        $this->requireAdminAuth($request);
        
        $data = json_decode($request->getContent(), true);
        $permissions = $data['permissions'] ?? [];
        
        $partner = $this->adminService->updatePartnerPermissions($partnerId, $permissions);
        
        return new JsonResponse([
            'success' => true,
            'partner' => $partner->toArray(),
            'message' => 'Partner permissions updated successfully'
        ]);
    }

    public function updateRevenueShare(Request $request, string $partnerId): JsonResponse
    {
        $this->requireAdminAuth($request);
        
        $data = json_decode($request->getContent(), true);
        $revenueShare = (float) ($data['revenue_share'] ?? 0.5);
        $reason = $data['reason'] ?? '';
        
        $partner = $this->adminService->updateRevenueShare($partnerId, $revenueShare, $reason);
        
        return new JsonResponse([
            'success' => true,
            'partner' => $partner->toArray(),
            'message' => "Revenue share updated to {$revenueShare}%"
        ]);
    }

    // ============================================================================
    // ADVERTISEMENT MANAGEMENT
    // ============================================================================

    public function advertisementsIndex(Request $request): Response
    {
        $this->requireAdminAuth($request);
        
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 20);
        $status = $request->query->get('status', 'all');
        
        $advertisements = $this->advertisementService->getAdvertisementsForAdmin([
            'page' => $page,
            'limit' => $limit,
            'status' => $status
        ]);
        
        return new Response($this->twig->render('admin/advertisements/index.html.twig', [
            'advertisements' => $advertisements,
            'filters' => compact('status'),
            'title' => 'Advertisement Management'
        ]));
    }

    public function createAdvertisement(Request $request): Response
    {
        $this->requireAdminAuth($request);
        
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $files = $request->files->all();
            
            $advertisement = $this->advertisementService->createAdvertisement($data, $files);
            
            return new JsonResponse([
                'success' => true,
                'advertisement' => $advertisement->toArray(),
                'message' => 'Advertisement created successfully'
            ]);
        }
        
        return new Response($this->twig->render('admin/advertisements/create.html.twig', [
            'title' => 'Create Advertisement'
        ]));
    }

    public function editAdvertisement(Request $request, int $adId): Response
    {
        $this->requireAdminAuth($request);
        
        $advertisement = $this->advertisementService->getAdvertisementById($adId);
        if (!$advertisement) {
            throw new \Exception('Advertisement not found', 404);
        }
        
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $files = $request->files->all();
            
            $advertisement = $this->advertisementService->updateAdvertisement($adId, $data, $files);
            
            return new JsonResponse([
                'success' => true,
                'advertisement' => $advertisement->toArray(),
                'message' => 'Advertisement updated successfully'
            ]);
        }
        
        return new Response($this->twig->render('admin/advertisements/edit.html.twig', [
            'advertisement' => $advertisement,
            'title' => "Edit Advertisement: {$advertisement->getTitle()}"
        ]));
    }

    public function toggleAdvertisement(Request $request, int $adId): JsonResponse
    {
        $this->requireAdminAuth($request);
        
        $advertisement = $this->advertisementService->toggleAdvertisement($adId);
        
        return new JsonResponse([
            'success' => true,
            'advertisement' => $advertisement->toArray(),
            'message' => 'Advertisement status updated'
        ]);
    }

    public function advertisementAnalytics(Request $request, int $adId): Response
    {
        $this->requireAdminAuth($request);
        
        $advertisement = $this->advertisementService->getAdvertisementById($adId);
        if (!$advertisement) {
            throw new \Exception('Advertisement not found', 404);
        }
        
        $analytics = $this->analyticsService->getAdvertisementAnalytics($adId, [
            'period' => $request->query->get('period', '30d')
        ]);
        
        return new Response($this->twig->render('admin/advertisements/analytics.html.twig', [
            'advertisement' => $advertisement,
            'analytics' => $analytics,
            'title' => "Analytics: {$advertisement->getTitle()}"
        ]));
    }

    // ============================================================================
    // SYSTEM CONFIGURATION
    // ============================================================================

    public function systemConfig(Request $request): Response
    {
        $this->requireAdminAuth($request);
        
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $this->configService->updateSystemConfig($data);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'System configuration updated successfully'
            ]);
        }
        
        $config = $this->configService->getAllSystemConfig();
        
        return new Response($this->twig->render('admin/config/system.html.twig', [
            'config' => $config,
            'title' => 'System Configuration'
        ]));
    }

    public function adConfig(Request $request): Response
    {
        $this->requireAdminAuth($request);
        
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $this->configService->updateAdConfig($data);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Advertisement configuration updated successfully'
            ]);
        }
        
        $config = $this->configService->getAdConfig();
        
        return new Response($this->twig->render('admin/config/advertisements.html.twig', [
            'config' => $config,
            'title' => 'Advertisement Configuration'
        ]));
    }

    public function revenueConfig(Request $request): Response
    {
        $this->requireAdminAuth($request);
        
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $this->configService->updateRevenueConfig($data);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Revenue configuration updated successfully'
            ]);
        }
        
        $config = $this->configService->getRevenueConfig();
        
        return new Response($this->twig->render('admin/config/revenue.html.twig', [
            'config' => $config,
            'title' => 'Revenue Configuration'
        ]));
    }

    public function securityConfig(Request $request): Response
    {
        $this->requireAdminAuth($request);
        
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $this->configService->updateSecurityConfig($data);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Security configuration updated successfully'
            ]);
        }
        
        $config = $this->configService->getSecurityConfig();
        
        return new Response($this->twig->render('admin/config/security.html.twig', [
            'config' => $config,
            'title' => 'Security Configuration'
        ]));
    }

    // ============================================================================
    // SUBSCRIPTION PLAN MANAGEMENT
    // ============================================================================

    public function subscriptionPlans(Request $request): Response
    {
        $this->requireAdminAuth($request);
        
        $plans = $this->adminService->getAllSubscriptionPlans();
        
        return new Response($this->twig->render('admin/plans/index.html.twig', [
            'plans' => $plans,
            'title' => 'Subscription Plans'
        ]));
    }

    public function createSubscriptionPlan(Request $request): Response
    {
        $this->requireAdminAuth($request);
        
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $plan = $this->adminService->createSubscriptionPlan($data);
            
            return new JsonResponse([
                'success' => true,
                'plan' => $plan->toArray(),
                'message' => 'Subscription plan created successfully'
            ]);
        }
        
        return new Response($this->twig->render('admin/plans/create.html.twig', [
            'title' => 'Create Subscription Plan'
        ]));
    }

    public function editSubscriptionPlan(Request $request, string $planId): Response
    {
        $this->requireAdminAuth($request);
        
        $plan = $this->adminService->getSubscriptionPlan($planId);
        if (!$plan) {
            throw new \Exception('Subscription plan not found', 404);
        }
        
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $plan = $this->adminService->updateSubscriptionPlan($planId, $data);
            
            return new JsonResponse([
                'success' => true,
                'plan' => $plan->toArray(),
                'message' => 'Subscription plan updated successfully'
            ]);
        }
        
        return new Response($this->twig->render('admin/plans/edit.html.twig', [
            'plan' => $plan,
            'title' => "Edit Plan: {$plan->getName()}"
        ]));
    }

    // ============================================================================
    // ANALYTICS & REPORTING
    // ============================================================================

    public function analyticsOverview(Request $request): Response
    {
        $this->requireAdminAuth($request);
        
        $period = $request->query->get('period', '30d');
        $analytics = $this->analyticsService->getSystemAnalytics(['period' => $period]);
        
        return new Response($this->twig->render('admin/analytics/overview.html.twig', [
            'analytics' => $analytics,
            'period' => $period,
            'title' => 'System Analytics'
        ]));
    }

    public function revenueReport(Request $request): Response
    {
        $this->requireAdminAuth($request);
        
        $period = $request->query->get('period', '30d');
        $report = $this->analyticsService->getRevenueReport(['period' => $period]);
        
        return new Response($this->twig->render('admin/analytics/revenue.html.twig', [
            'report' => $report,
            'period' => $period,
            'title' => 'Revenue Report'
        ]));
    }

    public function exportReport(Request $request): Response
    {
        $this->requireAdminAuth($request);
        
        $type = $request->query->get('type', 'partners');
        $format = $request->query->get('format', 'csv');
        $period = $request->query->get('period', '30d');
        
        $report = $this->analyticsService->exportReport($type, $format, ['period' => $period]);
        
        return new Response(
            $report['content'],
            200,
            [
                'Content-Type' => $report['mime_type'],
                'Content-Disposition' => "attachment; filename=\"{$report['filename']}\""
            ]
        );
    }

    // ============================================================================
    // SECURITY & AUDIT
    // ============================================================================

    public function securityEvents(Request $request): Response
    {
        $this->requireAdminAuth($request);
        
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 50);
        $severity = $request->query->get('severity', 'all');
        $eventType = $request->query->get('event_type', 'all');
        
        $events = $this->adminService->getSecurityEvents([
            'page' => $page,
            'limit' => $limit,
            'severity' => $severity,
            'event_type' => $eventType
        ]);
        
        return new Response($this->twig->render('admin/security/events.html.twig', [
            'events' => $events,
            'filters' => compact('severity', 'eventType'),
            'title' => 'Security Events'
        ]));
    }

    public function auditLog(Request $request): Response
    {
        $this->requireAdminAuth($request);
        
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 50);
        $action = $request->query->get('action', 'all');
        $userId = $request->query->get('user_id', '');
        
        $logs = $this->adminService->getAuditLogs([
            'page' => $page,
            'limit' => $limit,
            'action' => $action,
            'user_id' => $userId
        ]);
        
        return new Response($this->twig->render('admin/security/audit.html.twig', [
            'logs' => $logs,
            'filters' => compact('action', 'userId'),
            'title' => 'Audit Log'
        ]));
    }

    // ============================================================================
    // SYSTEM MAINTENANCE
    // ============================================================================

    public function systemMaintenance(Request $request): Response
    {
        $this->requireAdminAuth($request);
        
        $systemStatus = $this->adminService->getSystemStatus();
        
        return new Response($this->twig->render('admin/maintenance/index.html.twig', [
            'status' => $systemStatus,
            'title' => 'System Maintenance'
        ]));
    }

    public function clearCache(Request $request): JsonResponse
    {
        $this->requireAdminAuth($request);
        
        $cacheType = $request->request->get('cache_type', 'all');
        $result = $this->adminService->clearCache($cacheType);
        
        return new JsonResponse([
            'success' => true,
            'message' => "Cache cleared: {$cacheType}",
            'details' => $result
        ]);
    }

    public function runMaintenance(Request $request): JsonResponse
    {
        $this->requireAdminAuth($request);
        
        $task = $request->request->get('task');
        $result = $this->adminService->runMaintenanceTask($task);
        
        return new JsonResponse([
            'success' => true,
            'message' => "Maintenance task completed: {$task}",
            'details' => $result
        ]);
    }

    // ============================================================================
    // BLACKLIST/WHITELIST MANAGEMENT
    // ============================================================================

    public function partnerBlacklist(Request $request): Response
    {
        $this->requireAdminAuth($request);
        
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $this->adminService->updatePartnerBlacklist($data);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Partner blacklist updated successfully'
            ]);
        }
        
        $blacklist = $this->adminService->getPartnerBlacklist();
        
        return new Response($this->twig->render('admin/security/blacklist.html.twig', [
            'blacklist' => $blacklist,
            'title' => 'Partner Blacklist'
        ]));
    }

    public function domainBlacklist(Request $request): Response
    {
        $this->requireAdminAuth($request);
        
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $this->adminService->updateDomainBlacklist($data);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Domain blacklist updated successfully'
            ]);
        }
        
        $blacklist = $this->adminService->getDomainBlacklist();
        
        return new Response($this->twig->render('admin/security/domain-blacklist.html.twig', [
            'blacklist' => $blacklist,
            'title' => 'Domain Blacklist'
        ]));
    }

    // ============================================================================
    // HELPER METHODS
    // ============================================================================

    private function requireAdminAuth(Request $request): void
    {
        $user = $request->attributes->get('admin_user');
        if (!$user || !$user->isAdmin()) {
            throw new \Exception('Admin authentication required', 403);
        }
    }
}