// Chart.js code to display the number of all operators vs. operators in planning
// Assumes you have a canvas with id 'operatorsChart' in your dashboard HTML
document.addEventListener('DOMContentLoaded', function () {
    const operatorsChartCanvas = document.getElementById('operatorsChart');
    if (operatorsChartCanvas) {
        fetch('../php/dashboard_data.php?type=operators_chart')
            .then(response => response.json())
            .then(data => {
                const ctx = operatorsChartCanvas.getContext('2d');
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['Tous les opérateurs', 'Opérateurs dans le planning'],
                        datasets: [{
                            label: 'Nombre',
                            data: [data.all_operators, data.planned_operators],
                            backgroundColor: [
                                'rgba(54, 162, 235, 0.7)',
                                'rgba(75, 192, 192, 0.7)'
                            ],
                            borderColor: [
                                'rgba(54, 162, 235, 1)',
                                'rgba(75, 192, 192, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                            title: {
                                display: true,
                                text: 'Comparaison: Tous les opérateurs vs Opérateurs dans le planning'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                precision: 0
                            }
                        }
                    }
                });
            });
    }
});
