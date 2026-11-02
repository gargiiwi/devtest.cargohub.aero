  // Get references to tabs and content boxes
  const tab1 = document.getElementById('tab1');
  const tab2 = document.getElementById('tab2');
  const box1 = document.getElementById('box1');
  const box2 = document.getElementById('box2');

  // Function to show the content of the selected tab
  function showTabContent(tab, box) {
      // Remove active class from all tabs and boxes
      tab1.classList.remove('active');
      tab2.classList.remove('active');
      box1.classList.remove('active');
      box2.classList.remove('active');

      // Add active class to the selected tab and its content
      tab.classList.add('active');
      box.classList.add('active');
  }

  // Event listeners for tab clicks
  tab1.addEventListener('click', () => showTabContent(tab1, box1));
  tab2.addEventListener('click', () => showTabContent(tab2, box2));