
import React from 'react';
import { Home, User, Server, ShoppingBag } from 'lucide-react';
import { AppView } from '../types';

interface Props {
  activeView: AppView;
  setView: (view: AppView) => void;
  theme: 'light' | 'dark';
}

const Navigation: React.FC<Props> = ({ activeView, setView, theme }) => {
  const isDark = theme === 'dark';
  const navItems = [
    { view: AppView.DASHBOARD, icon: <Home size={24} />, label: '首页' },
    { view: AppView.NODES, icon: <Server size={24} />, label: '节点' },
    { view: AppView.SHOP, icon: <ShoppingBag size={24} />, label: '商店' },
    { view: AppView.PROFILE, icon: <User size={24} />, label: '我的' },
  ];

  return (
    <nav className={`absolute bottom-6 left-6 right-6 transition-all duration-500 backdrop-blur-xl border rounded-3xl h-18 shadow-2xl flex items-center justify-around px-4 z-50 ${
      isDark 
      ? 'bg-slate-900/80 border-slate-800 shadow-black/40' 
      : 'bg-white/80 border-slate-200/50 shadow-slate-200/50'
    }`}>
      {navItems.map((item) => {
        const isActive = activeView === item.view;
        return (
          <button
            key={item.view}
            onClick={() => setView(item.view)}
            className={`flex flex-col items-center justify-center w-16 h-16 transition-all relative ${
              isActive 
                ? (isDark ? 'text-emerald-400' : 'text-emerald-500') 
                : (isDark ? 'text-slate-600 hover:text-slate-400' : 'text-slate-400 hover:text-slate-600')
            }`}
          >
            {isActive && (
              <span className={`absolute top-2 w-1.5 h-1.5 rounded-full ${isDark ? 'bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,0.6)]' : 'bg-emerald-500'}`} />
            )}
            <div className={`transition-transform duration-300 ${isActive ? 'scale-110 -translate-y-1' : ''}`}>
              {item.icon}
            </div>
            <span className={`text-[10px] font-bold mt-1 transition-opacity ${isActive ? 'opacity-100' : 'opacity-0 h-0 overflow-hidden'}`}>
              {item.label}
            </span>
          </button>
        );
      })}
    </nav>
  );
};

export default Navigation;
