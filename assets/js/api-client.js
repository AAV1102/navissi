/**
 * API CLIENT UNIFICADO - WorkManager ERP v2.0
 * ======================================
 * Cliente JavaScript unificado para todas las APIs
 */

class APIClient {
    constructor() {
        this.baseURL = '/api';
        this.defaultHeaders = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseURL}/${endpoint.replace(/^\//, '')}`;

        const config = {
            method: options.method || 'GET',
            headers: {
                ...this.defaultHeaders,
                ...options.headers
            },
            ...options
        };

        if (config.method !== 'GET' && options.data) {
            config.body = JSON.stringify(options.data);
        }

        try {
            const response = await fetch(url, config);
            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || `HTTP error! status: ${response.status}`);
            }

            return data;
        } catch (error) {
            console.error('API Request failed:', error);
            throw error;
        }
    }

    // Auth methods
    async login(username, password) {
        return this.request('auth/login', {
            method: 'POST',
            data: { username, password }
        });
    }

    async logout() {
        return this.request('auth/logout', {
            method: 'POST'
        });
    }

    async verifyAuth() {
        return this.request('auth/verify');
    }

    async refreshSession() {
        return this.request('auth/refresh', {
            method: 'POST'
        });
    }

    // Admin methods
    async getAdminStats() {
        return this.request('admin/stats');
    }

    async getRecentActivity() {
        return this.request('admin/recent-activity');
    }

    async getSystemStatus() {
        return this.request('admin/system-status');
    }

    async getUsers() {
        return this.request('admin/users');
    }

    async createUser(userData) {
        return this.request('admin/users', {
            method: 'POST',
            data: userData
        });
    }

    async updateUser(userId, userData) {
        return this.request(`admin/users/${userId}`, {
            method: 'PUT',
            data: userData
        });
    }

    async deleteUser(userId) {
        return this.request(`admin/users/${userId}`, {
            method: 'DELETE'
        });
    }

    // Usuarios methods
    async getUsuariosStats() {
        return this.request('usuarios/stats');
    }

    async getUsuariosByRole() {
        return this.request('usuarios/by-role');
    }

    async getUsuariosByDepartment() {
        return this.request('usuarios/by-department');
    }

    async getRecentUsers() {
        return this.request('usuarios/recent');
    }

    async getUserActivity() {
        return this.request('usuarios/activity');
    }

    // Inventario methods
    async getInventarioStats() {
        return this.request('inventario/stats');
    }

    async getEquipmentByCategory() {
        return this.request('inventario/by-category');
    }

    async getEquipmentByLocation() {
        return this.request('inventario/by-location');
    }

    async getRecentMovements() {
        return this.request('inventario/recent-movements');
    }

    async getLowStockAlerts() {
        return this.request('inventario/low-stock');
    }

    // Tickets methods
    async getTicketsStats() {
        return this.request('tickets/stats');
    }

    async getTicketsByPriority() {
        return this.request('tickets/by-priority');
    }

    async getTicketsByCategory() {
        return this.request('tickets/by-category');
    }

    async getRecentTickets() {
        return this.request('tickets/recent');
    }

    async getMyAssignedTickets() {
        return this.request('tickets/my-assigned');
    }

    async getPerformanceMetrics() {
        return this.request('tickets/performance-metrics');
    }

    // Sistemas methods
    async getSistemasStats() {
        return this.request('sistemas/stats');
    }

    async getNetworkMap() {
        return this.request('sistemas/network-map');
    }

    async getSystemAlerts() {
        return this.request('sistemas/alerts');
    }

    async getRecentSistemasTickets() {
        return this.request('sistemas/recent-tickets');
    }

    // Sedes methods
    async getSedesStats() {
        return this.request('sedes/stats');
    }

    async getSedesList() {
        return this.request('sedes/list');
    }

    async getSedeDetails(sedeId) {
        return this.request(`sedes/details?id=${sedeId}`);
    }

    // Reportes methods
    async getTicketsByMonth() {
        return this.request('reportes/tickets-by-month');
    }

    async getEquipmentStatus() {
        return this.request('reportes/equipment-status');
    }

    async getUserActivityReport() {
        return this.request('reportes/user-activity');
    }

    async getRecentReports() {
        return this.request('reportes/recent');
    }

    async getScheduledReports() {
        return this.request('reportes/scheduled');
    }

    // Generic methods for dynamic modules
    async getModuleStats(module) {
        return this.request(`${module}/stats`);
    }

    async getModuleData(module, endpoint) {
        return this.request(`${module}/${endpoint}`);
    }

    async postModuleData(module, endpoint, data) {
        return this.request(`${module}/${endpoint}`, {
            method: 'POST',
            data: data
        });
    }

    // Health check
    async healthCheck() {
        return this.request('health');
    }
}

// Create global instance
window.apiClient = new APIClient();

// Export for modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = APIClient;
}
