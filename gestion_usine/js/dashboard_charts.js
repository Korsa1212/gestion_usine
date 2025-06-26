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
    
    // Machine Usage Area Chart
    const machineUsageChartCanvas = document.getElementById('machineUsageChart');
    if (machineUsageChartCanvas) {
        // Set explicit height constraint to control the chart size
        machineUsageChartCanvas.style.height = '300px';
        fetch('../php/dashboard_data.php?type=machine_usage')
            .then(response => response.json())
            .then(data => {
                // Create an array to simulate a time series for area chart
                const timeLabels = [];
                for (let i = 1; i <= 10; i++) {
                    timeLabels.push('Point ' + i);
                }
                
                // Transform data to be suitable for area chart
                const datasets = [];
                const colors = [
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(255, 99, 132, 0.7)'
                ];
                
                // Generate a test dataset for each machine
                for (let i = 0; i < data.labels.length; i++) {
                    const machineData = [];
                    // Scale down the values to be more readable
                    const baseValue = data.data[i] > 10 ? 10 : data.data[i]; // Cap the value at 10 for better visualization
                    
                    // Generate smooth data for area visualization
                    for (let j = 0; j < timeLabels.length; j++) {
                        // Create a more moderate curve with less extreme peaks
                        const pointValue = baseValue * 0.5 * (1 + Math.sin((j / (timeLabels.length - 1)) * Math.PI));
                        machineData.push(Math.max(0, pointValue));
                    }
                    
                    datasets.push({
                        label: data.labels[i],
                        data: machineData,
                        backgroundColor: colors[i % colors.length],
                        borderColor: colors[i % colors.length].replace('0.7', '1'),
                        borderWidth: 1,
                        fill: true,
                        tension: 0.4
                    });
                }
                
                const ctx = machineUsageChartCanvas.getContext('2d');
                new Chart(ctx, {
                    type: 'line', // Line chart is the base for area charts
                    data: {
                        labels: timeLabels,
                        datasets: datasets
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        height: 300, // Explicit height in pixels
                        aspectRatio: 2, // Width to height ratio
                        plugins: {
                            filler: {
                                propagate: true
                            },
                            title: {
                                display: true,
                                text: 'Utilisation des machines en fabrication'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        hover: {
                            mode: 'nearest',
                            intersect: true
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                stacked: false,
                                title: {
                                    display: true,
                                    text: 'Niveau d\'utilisation'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Période'
                                }
                            }
                        }
                    }
                });
            });
    }
});
