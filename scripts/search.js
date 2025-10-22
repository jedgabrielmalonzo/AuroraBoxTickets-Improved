document.getElementById('searchInput').addEventListener('input', function() {
    const query = this.value.trim();
    const resultsDiv = document.getElementById('search-results');

    if (query.length === 0) {
        resultsDiv.innerHTML = ''; // Clear results when input is empty
        return;
    }

    // Fetch search suggestions (direct search)
    fetch('search.php?query=' + encodeURIComponent(query) + '&direct=true')
        .then(response => response.json())
        .then(data => {
            resultsDiv.innerHTML = ''; // Clear previous results

            if (data.results.length > 0) {
                const suggestionList = document.createElement('div');
                suggestionList.classList.add('suggestion-list');

                data.results.forEach(parks => {
                    const suggestionItem = document.createElement('a');
                    suggestionItem.href = `park_info.php?id=${parks.id}`;
                    suggestionItem.classList.add('suggestion-item');
                    suggestionItem.innerHTML = `<strong>${parks.name}</strong> - ${parks.city}`;
                    
                    suggestionItem.onclick = function() {
                        document.getElementById('searchInput').value = parks.name; // Set input to selected suggestion
                        resultsDiv.innerHTML = ''; // Clear suggestions
                    };

                    suggestionList.appendChild(suggestionItem);
                });

                resultsDiv.appendChild(suggestionList);
            } else {
                resultsDiv.innerHTML = `<p>No results found for "${query}"</p>`; // No results message
            }
        })
        .catch(error => console.error('Error:', error));
});

// ✅ Redirect on Enter key with Fuse.js fallback 
document.getElementById('searchForm').addEventListener('submit', function(e) {
    e.preventDefault(); // Prevent default form submission
    const query = document.getElementById('searchInput').value.trim();

    if (query.length > 0) {
        // Kung may parks[] loaded
    if (typeof parks !== "undefined" && parks.length > 0) {
        const fuse = new Fuse(parks, {
            keys: ["name", "city"],
            threshold: 0.5, // Lower threshold for more lenient matching
            distance: 100,
            minMatchCharLength: 2
        });
    
        const results = fuse.search(query);
    
        if (results.length > 0) {
            const ids = results.map(r => r.item.id);
            window.location.href = `search.php?fuzzy_ids=${ids.join(",")}`;
            return;
        }
    }
        // Fallback to normal search
        window.location.href = `search.php?query=${encodeURIComponent(query)}`;
    }
});


console.log("Search query:", query);
console.log("Fuse search results:", results);
