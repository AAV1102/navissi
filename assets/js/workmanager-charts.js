class WorkManagerCharts {
    constructor() {
        this.colors = {
            primary: "#2c3e50",
            secondary: "#3498db",
            success: "#27ae60",
            warning: "#f39c12",
            danger: "#e74c3c",
            info: "#17a2b8"
        };

        this.gradients = {
            primary: ["#667eea", "#764ba2"],
            success: ["#11998e", "#38ef7d"],
            warning: ["#f093fb", "#f5576c"],
            info: ["#4facfe", "#00f2fe"]
        };
    }

    // Gráfica de distribución de módulos
    createModuleDistribution(canvasId, data) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;

        new Chart(ctx, {
            type: "doughnut",
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: [
                        this.colors.primary,
                        this.colors.secondary,
                        this.colors.success,
                        this.colors.warning,
                        this.colors.danger,
                        this.colors.info
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: "bottom"
                    }
                }
            }
        });
    }

    // Gráfica de actividad del sistema
    createActivityChart(canvasId, data) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;

        const gradient = ctx.getContext("2d").createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, this.gradients.primary[0]);
        gradient.addColorStop(1, this.gradients.primary[1]);

        new Chart(ctx, {
            type: "line",
            data: {
                labels: data.labels,
                datasets: [{
                    label: "Actividad",
                    data: data.values,
                    borderColor: this.colors.primary,
                    backgroundColor: gradient,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Gráfica de barras para estadísticas
    createStatsChart(canvasId, data) {
        const ctx = document.getElementById(canvasId);
        if (!ctx) return;

        new Chart(ctx, {
            type: "bar",
            data: {
                labels: data.labels,
                datasets: [{
                    label: "Cantidad",
                    data: data.values,
                    backgroundColor: [
                        this.colors.primary,
                        this.colors.success,
                        this.colors.warning,
                        this.colors.danger
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Inicializar todas las gráficas
    initializeCharts() {
        // Datos de ejemplo para módulos
        const moduleData = {
            labels: ["Sistemas", "Inventario", "RH", "CRM", "Soporte", "Otros"],
            values: [25, 20, 15, 15, 15, 10]
        };

        // Datos de ejemplo para actividad
        const activityData = {
            labels: ["Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom"],
            values: [65, 59, 80, 81, 56, 55, 40]
        };

        // Datos de ejemplo para estadísticas
        const statsData = {
            labels: ["Usuarios", "Equipos", "Tickets", "Sedes"],
            values: [150, 300, 45, 8]
        };

        this.createModuleDistribution("moduleChart", moduleData);
        this.createActivityChart("activityChart", activityData);
        this.createStatsChart("statsChart", statsData);
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener("DOMContentLoaded", function () {
    const charts = new WorkManagerCharts();
    charts.initializeCharts();
});
