// tailwind.config.js
module.exports = {
  content: [
    './src/Views/*.php',  
    './style.css'             
  ],
  theme: {
    extend: {
      fontFamily: {
        Barlow: ['Barlow', 'sans-serif'],
        'SFPro': ['SF Pro Display', 'sans-serif'],
      }
},
  },
  plugins: [],
}
