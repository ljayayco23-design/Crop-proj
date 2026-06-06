@extends('layouts.admin')
@section('title', 'Admin Analytics Dashboard • CROPSENSE AI')

@section('content')
<div class="mb-4">
    <h4 class="fw-bold text-white mb-1">CROPSENSE AI • Admin Analytics Dashboard</h4>
    <p class="text-secondary mb-0">Real-time overview • {{ \Carbon\Carbon::now()->format('F j, Y') }}</p>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="prodigy-card p-4 h-100 position-relative overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(59,130,246,0.1), transparent); z-index:0;"></div>
            <div class="d-flex justify-content-between align-items-start position-relative z-1">
                <div>
                    <p class="text-secondary mb-1 small text-uppercase fw-bold">Total Farmers</p>
                    <h2 class="fw-bold text-white mb-0">{{ number_format($registeredFarmers ?? 0) }}</h2>
                </div>
                <div class="bg-primary bg-opacity-25 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width:50px;height:50px;font-size:20px;"><i class="fas fa-users"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="prodigy-card p-4 h-100 position-relative overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(16,185,129,0.1), transparent); z-index:0;"></div>
            <div class="d-flex justify-content-between align-items-start position-relative z-1">
                <div>
                    <p class="text-secondary mb-1 small text-uppercase fw-bold">Active Technicians</p>
                    <h2 class="fw-bold text-white mb-0">{{ number_format($activeTechnicians ?? 0) }}</h2>
                </div>
                <div class="bg-success bg-opacity-25 text-success rounded-3 d-flex align-items-center justify-content-center" style="width:50px;height:50px;font-size:20px;"><i class="fas fa-user-check"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="prodigy-card p-4 h-100 position-relative overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(245,158,11,0.1), transparent); z-index:0;"></div>
            <div class="d-flex justify-content-between align-items-start position-relative z-1">
                <div>
                    <p class="text-secondary mb-1 small text-uppercase fw-bold">Total Detections</p>
                    <h2 class="fw-bold text-white mb-0">{{ number_format($totalDetections ?? 0) }}</h2>
                </div>
                <div class="bg-warning bg-opacity-25 text-warning rounded-3 d-flex align-items-center justify-content-center" style="width:50px;height:50px;font-size:20px;"><i class="fas fa-camera-retro"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="prodigy-card p-4 h-100 position-relative overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(239,68,68,0.1), transparent); z-index:0;"></div>
            <div class="d-flex justify-content-between align-items-start position-relative z-1">
                <div>
                    <p class="text-secondary mb-1 small text-uppercase fw-bold">Pending Approvals</p>
                    <h2 class="fw-bold text-white mb-0">{{ number_format($pendingApprovals ?? 0) }}</h2>
                </div>
                <div class="bg-danger bg-opacity-25 text-danger rounded-3 d-flex align-items-center justify-content-center" style="width:50px;height:50px;font-size:20px;"><i class="fas fa-clock"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="prodigy-card h-100">
            <div class="p-4 border-bottom border-secondary d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold text-white mb-1">Detections Trend</h5>
                    <small class="text-success"><i class="fas fa-chart-line me-1"></i> Real-time Analytics</small>
                </div>
            </div>
            <div class="p-3">
                <div id="detectionChart" style="height: 350px;"></div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-4">
        <div class="prodigy-card h-100 p-4">
            <h5 class="fw-bold text-white mb-4"><i class="fas fa-bolt text-warning me-2"></i>Quick Actions</h5>
            <div class="d-grid gap-3">
                <a href="{{ route('admin.technician.create') }}" class="btn btn-primary py-3 text-start fw-bold shadow-sm rounded-3"><i class="fas fa-plus-circle fa-lg me-3"></i> Add Technician</a>
                <a href="{{ route('admin.announcement') }}" class="btn btn-info py-3 text-start fw-bold text-white shadow-sm rounded-3"><i class="fas fa-bullhorn fa-lg me-3"></i> Broadcast Message</a>
                <a href="{{ route('admin.knowledge.management') }}" class="btn btn-success py-3 text-start fw-bold shadow-sm rounded-3"><i class="fas fa-book fa-lg me-3"></i> Knowledge Base</a>
                <a href="{{ route('admin.history') }}" class="btn btn-warning py-3 text-start fw-bold text-dark shadow-sm rounded-3"><i class="fas fa-history fa-lg me-3"></i> View All History</a>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Initialize ApexChart with mock 6-month data to match the Prodigy look
    document.addEventListener("DOMContentLoaded", function() {
        new ApexCharts(document.querySelector("#detectionChart"), {
            series: [{ name: "Detections", data: [45, 52, 38, 85, 102, 60] }],
            chart: { type: 'area', height: 350, toolbar: { show: false }, background: 'transparent' },
            colors: ['#3b82f6'],
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.5, opacityTo: 0.1, stops: [0, 90] } },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 3 },
            xaxis: { categories: ['Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr'], labels: { style: { colors: '#94a3b8' } }, axisBorder: { show: false } },
            yaxis: { labels: { style: { colors: '#94a3b8' } } },
            grid: { borderColor: '#334155', strokeDashArray: 4 },
            tooltip: { theme: 'dark' }
        }).render();
    });
</script>
@endsection