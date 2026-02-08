
import React from 'react';

interface Props {
  className?: string;
  classNameBody?: string;
  classNameRoad?: string;
}

const Logo: React.FC<Props> = ({ 
  className = "w-full h-full", 
  classNameBody,
  classNameRoad
}) => {
  return (
    <svg viewBox="0 0 100 100" className={className} xmlns="http://www.w3.org/2000/svg" fill="none">
      {/* Abstract 'E' / Shield Container */}
      <path 
        d="M 78 28 
           L 38 28 
           C 28 28 24 32 24 42 
           L 24 58 
           C 24 68 28 72 38 72 
           L 78 72" 
        strokeWidth="14" 
        strokeLinecap="round" 
        strokeLinejoin="round" 
        className={classNameBody || "stroke-slate-900 dark:stroke-white"} 
      />
      
      {/* Central Connection Node */}
      <path 
        d="M 40 50 L 78 50" 
        strokeWidth="14" 
        strokeLinecap="round" 
        className={classNameRoad || "stroke-emerald-500"}
      />
      
      {/* Decorative dot for 'Network' feel */}
      <circle cx="78" cy="50" r="7" className={classNameRoad?.replace('stroke-', 'fill-') || "fill-emerald-500"} stroke="none" />
    </svg>
  );
};

export default Logo;
