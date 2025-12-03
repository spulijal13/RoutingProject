<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Maps App - Laravel</title>
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            height: 100vh;
            overflow: hidden;
        }

        #map {
            height: 100vh;
            width: 100%;
        }

        .controls-panel {
            position: absolute;
            top: 20px;
            left: 20px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            max-width: 400px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .controls-panel h2 {
            font-size: 20px;
            margin-bottom: 15px;
            color: #333;
        }

        .input-group {
            margin-bottom: 15px;
        }

        .input-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            font-weight: 500;
            color: #555;
        }

        .input-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .input-group input:focus {
            outline: none;
            border-color: #4285f4;
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 10px;
        }

        .btn-primary {
            background: #4285f4;
            color: white;
        }

        .btn-primary:hover {
            background: #3367d6;
        }

        .btn-secondary {
            background: #f1f3f4;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .route-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .route-info h3 {
            font-size: 16px;
            margin-bottom: 10px;
            color: #333;
        }

        .route-stat {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .route-stat:last-child {
            border-bottom: none;
        }

        .route-stat span:first-child {
            color: #666;
            font-size: 14px;
        }

        .route-stat span:last-child {
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .search-results {
            max-height: 200px;
            overflow-y: auto;
            margin-top: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
        }

        .search-result-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
        }

        .search-result-item:hover {
            background: #f8f9fa;
        }

        .search-result-item:last-child {
            border-bottom: none;
        }

        .location-btn {
            position: absolute;
            bottom: 120px;
            right: 20px;
            background: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            cursor: pointer;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .location-btn:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        .saved-routes {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e0e0e0;
        }

        .saved-route-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .saved-route-item:hover {
            background: #e9ecef;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 10px;
            color: #666;
        }

        .loading.active {
            display: block;
        }
        
        .input-group input.selected {
            border-color: #34a853;
            background-color: #f0f9f4;
        }

        .search-result-item {
            padding: 10px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.2s;
            font-size: 14px;
        }

        .search-result-item:hover {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <div id="map"></div>
    
    <button class="location-btn" id="currentLocationBtn" title="Current Location">📍</button>

    <div class="controls-panel">
        <h2>🗺️ Route Planner</h2>
        
        <div class="input-group">
            <label for="startSearch">Starting Point</label>
            <input type="text" id="startSearch" placeholder="Search for starting location...">
            <div id="startSearchResults" class="search-results" style="display: none;"></div>
        </div>

        <div class="input-group">
            <label for="endSearch">Destination</label>
            <input type="text" id="endSearch" placeholder="Search for destination...">
            <div id="endSearchResults" class="search-results" style="display: none;"></div>
        </div>

        <button class="btn btn-primary" id="calculateRouteBtn">Calculate Route</button>
        <button class="btn btn-secondary" id="clearRouteBtn">Clear Route</button>

        <div class="loading" id="loading">Calculating route...</div>

        <div id="routeInfo" style="display: none;"></div>

        @auth
        <div class="saved-routes">
            <h3>Recent Routes</h3>
            @forelse($routes as $route)
                <div class="saved-route-item" 
                     data-start-lat="{{ $route->start_lat }}" 
                     data-start-lng="{{ $route->start_lng }}"
                     data-end-lat="{{ $route->end_lat }}" 
                     data-end-lng="{{ $route->end_lng }}">
                    <strong>{{ $route->name ?? 'Unnamed Route' }}</strong><br>
                    <small>{{ $route->formatted_distance }} • {{ $route->formatted_duration }}</small>
                </div>
            @empty
                <p style="color: #999; font-size: 14px;">No saved routes yet</p>
            @endforelse
        </div>
        @endauth
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
        // Initialize map
        const map = L.map('map').setView([34.0522, -118.2437], 10); // Los Angeles area

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(map);

        let startMarker = null;
        let endMarker = null;
        let routeLine = null;
        let startCoords = null;
        let endCoords = null;
        let currentRoute = null;

        // Get user's current location
        document.getElementById('currentLocationBtn').addEventListener('click', () => {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        map.setView([lat, lng], 15);
                        
                        if (startMarker) startMarker.remove();
                        startMarker = L.marker([lat, lng]).addTo(map)
                            .bindPopup('Your Location').openPopup();
                        startCoords = { lat, lng };
                        
                        // Mark input as selected
                        document.getElementById('startSearch').value = 'Current Location';
                        document.getElementById('startSearch').classList.add('selected');
                        
                        console.log('Current location set:', startCoords);
                    },
                    (error) => {
                        alert('Could not get your location: ' + error.message);
                    }
                );
            } else {
                alert('Geolocation is not supported by your browser');
            }
        });

        // Search functionality
        let searchTimeout;

        function setupSearch(inputId, resultsId, isStart) {
            const input = document.getElementById(inputId);
            const resultsDiv = document.getElementById(resultsId);
            
            input.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                const query = e.target.value.trim();
                
                // Remove selected class when user types
                input.classList.remove('selected');
                
                if (query.length < 2) {
                    resultsDiv.style.display = 'none';
                    return;
                }
                
                searchTimeout = setTimeout(() => {
                    searchLocation(query, resultsDiv, isStart);
                }, 300);
            });
            
            // Clear coordinates when input is cleared
            input.addEventListener('change', (e) => {
                if (e.target.value.trim() === '') {
                    if (isStart) {
                        startCoords = null;
                        if (startMarker) startMarker.remove();
                        startMarker = null;
                    } else {
                        endCoords = null;
                        if (endMarker) endMarker.remove();
                        endMarker = null;
                    }
                }
            });
        }

        function searchLocation(query, resultsDiv, isStart) {
            console.log('Searching for:', query);
            const url = `/maps/search?query=${encodeURIComponent(query)}`;
            console.log('Fetch URL:', url);
            
            fetch(url)
                .then(res => {
                    console.log('Response status:', res.status);
                    if (!res.ok && res.status !== 404) {
                        throw new Error('Network response was not ok');
                    }
                    return res.json();
                })
                .then(data => {
                    console.log('Search results:', data);
                    
                    if (data.success && data.results && data.results.length > 0) {
                        resultsDiv.innerHTML = '';
                        
                        // Show fallback notice if using fallback
                        if (data.fallback) {
                            const notice = document.createElement('div');
                            notice.style.padding = '8px 10px';
                            notice.style.fontSize = '12px';
                            notice.style.color = '#856404';
                            notice.style.backgroundColor = '#fff3cd';
                            notice.style.borderBottom = '1px solid #ffc107';
                            notice.textContent = '📍 Showing common locations';
                            resultsDiv.appendChild(notice);
                        }
                        
                        data.results.forEach(result => {
                            const div = document.createElement('div');
                            div.className = 'search-result-item';
                            div.textContent = result.name;
                            div.addEventListener('click', () => {
                                selectLocation(result, isStart);
                                resultsDiv.style.display = 'none';
                                document.getElementById(isStart ? 'startSearch' : 'endSearch').value = result.name;
                            });
                            resultsDiv.appendChild(div);
                        });
                        resultsDiv.style.display = 'block';
                    } else {
                        console.log('No results found');
                        resultsDiv.innerHTML = `
                            <div class="search-result-item" style="color: #666; cursor: default;">
                                ${data.message || 'No results found. Try: "Los Angeles", "San Francisco", etc.'}
                            </div>
                        `;
                        resultsDiv.style.display = 'block';
                    }
                })
                .catch(err => {
                    console.error('Search error:', err);
                    resultsDiv.innerHTML = `
                        <div class="search-result-item" style="color: #dc3545; cursor: default;">
                            Connection error. Please check your internet connection.
                        </div>
                    `;
                    resultsDiv.style.display = 'block';
                });
        }


        function selectLocation(location, isStart) {
            const marker = L.marker([location.lat, location.lng]).addTo(map);
            marker.bindPopup(location.name).openPopup();
            map.setView([location.lat, location.lng], 14);
            
            const inputField = document.getElementById(isStart ? 'startSearch' : 'endSearch');
            inputField.classList.add('selected');
            
            if (isStart) {
                if (startMarker) startMarker.remove();
                startMarker = marker;
                startCoords = { lat: location.lat, lng: location.lng };
                console.log('Start location set:', startCoords);
            } else {
                if (endMarker) endMarker.remove();
                endMarker = marker;
                endCoords = { lat: location.lat, lng: location.lng };
                console.log('End location set:', endCoords);
            }
        }

        setupSearch('startSearch', 'startSearchResults', true);
        setupSearch('endSearch', 'endSearchResults', false);

        // Calculate route
        document.getElementById('calculateRouteBtn').addEventListener('click', () => {
            console.log('Calculate button clicked');
            console.log('Start coords:', startCoords);
            console.log('End coords:', endCoords);
            
            if (!startCoords || !endCoords) {
                alert('Please search and SELECT both start and end locations from the dropdown results');
                return;
            }

            document.getElementById('loading').classList.add('active');
            document.getElementById('routeInfo').style.display = 'none';
            
            fetch('/maps/route', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    start_lat: startCoords.lat,
                    start_lng: startCoords.lng,
                    end_lat: endCoords.lat,
                    end_lng: endCoords.lng
                })
            })
            .then(res => {
                if (!res.ok) {
                    throw new Error('Network response was not ok');
                }
                return res.json();
            })
            .then(data => {
                document.getElementById('loading').classList.remove('active');
                
                if (data.success) {
                    currentRoute = data.route;
                    displayRoute(data.route);
                } else {
                    alert('Could not calculate route: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(err => {
                document.getElementById('loading').classList.remove('active');
                console.error('Route error:', err);
                alert('Error calculating route. Please check console for details.');
            });
        });

        function displayRoute(route) {
            // Remove existing route
            if (routeLine) routeLine.remove();
            
            // Draw route on map
            const coords = route.geometry.coordinates.map(coord => [coord[1], coord[0]]);
            routeLine = L.polyline(coords, { color: '#4285f4', weight: 5 }).addTo(map);
            map.fitBounds(routeLine.getBounds());
            
            // Display route info
            const routeInfoDiv = document.getElementById('routeInfo');
            const distance = route.distance.toFixed(2);
            const hours = Math.floor(route.duration / 3600);
            const minutes = Math.floor((route.duration % 3600) / 60);
            const durationText = hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
            
            routeInfoDiv.innerHTML = `
                <div class="route-info">
                    <h3>Route Details</h3>
                    <div class="route-stat">
                        <span>Distance:</span>
                        <span>${distance} km</span>
                    </div>
                    <div class="route-stat">
                        <span>Duration:</span>
                        <span>${durationText}</span>
                    </div>
                </div>
            `;
            routeInfoDiv.style.display = 'block';
        }

        // Clear route
        document.getElementById('clearRouteBtn').addEventListener('click', () => {
            if (startMarker) startMarker.remove();
            if (endMarker) endMarker.remove();
            if (routeLine) routeLine.remove();
            
            startMarker = null;
            endMarker = null;
            routeLine = null;
            startCoords = null;
            endCoords = null;
            currentRoute = null;
            
            document.getElementById('startSearch').value = '';
            document.getElementById('endSearch').value = '';
            document.getElementById('startSearch').classList.remove('selected');
            document.getElementById('endSearch').classList.remove('selected');
            document.getElementById('routeInfo').style.display = 'none';
            document.getElementById('startSearchResults').style.display = 'none';
            document.getElementById('endSearchResults').style.display = 'none';
        });

        // Load saved routes
        document.querySelectorAll('.saved-route-item').forEach(item => {
            item.addEventListener('click', () => {
                const startLat = parseFloat(item.dataset.startLat);
                const startLng = parseFloat(item.dataset.startLng);
                const endLat = parseFloat(item.dataset.endLat);
                const endLng = parseFloat(item.dataset.endLng);
                
                startCoords = { lat: startLat, lng: startLng };
                endCoords = { lat: endLat, lng: endLng };
                
                if (startMarker) startMarker.remove();
                if (endMarker) endMarker.remove();
                
                startMarker = L.marker([startLat, startLng]).addTo(map).bindPopup('Start');
                endMarker = L.marker([endLat, endLng]).addTo(map).bindPopup('End');
                
                document.getElementById('startSearch').classList.add('selected');
                document.getElementById('endSearch').classList.add('selected');
                
                document.getElementById('calculateRouteBtn').click();
            });
        });

        // Hide search results when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.input-group')) {
                document.getElementById('startSearchResults').style.display = 'none';
                document.getElementById('endSearchResults').style.display = 'none';
            }
        });
    </script>
</body>
</html>