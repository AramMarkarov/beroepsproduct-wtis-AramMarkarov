<?php

echo "<script>
tailwind.config = {
  theme: {
    extend: {
      backgroundImage: {
        'hero-image': \"url('/images/backgroundTwo.jpg')\",
      },
      colors: {
        'text': '#0b1627',
        'background': '#b9cded',
        'hover': '#7abb8b',
        'nav': '#edb9a3',
        }, 
      fontSize: {
        'headline': '2.5rem', // 40 pt
        'title': '1.875rem', // 30 pt
        'subheader': '1.25rem', // 20 pt
        'quote': '1.25rem', // 20 pt
        'body': '0.875rem', // 14 pt
        'secondary-body': '0.75rem', //12 pt
        'button': '0.875rem', // 14 pt
      },
     },
  },
}
</script>";