
import React from 'react';
import { ChevronLeft, Search, Wifi, SignalHigh, SignalLow, Globe } from 'lucide-react';
import { ProxyNode } from '../types';

interface Props {
  onBack: () => void;
  theme: 'light' | 'dark';
}

const Nodes: React.FC<Props> = ({ onBack, theme }) => {
  const isDark = theme === 'dark';
  const nodes: ProxyNode[] = [
    { id: '1', name: '新加坡高速 01', country: 'Singapore', type: 'SS', latency: 45 },
    { id: '2', name: '日本东京 BGP', country: 'Japan', type: 'V2', latency: 68 },
    { id: '3', name: '美国洛杉矶 05', country: 'USA', type: 'SS', latency: 156 },
    { id: '4', name: '香港原生 IP 02', country: 'Hong Kong', type: 'Trojan', latency: 32 },
    { id: '5', name: '韩国首尔专线', country: 'South Korea', type: 'SS', latency: 89 },
    { id: '6', name: '台湾流媒体解锁', country: 'Taiwan', type: 'V2', latency: 41 },
    { id: '7', name: '英国伦敦节点', country: 'UK', type: 'SS', latency: 210 },
  ];

  const getLatencyColor = (ms: number) => {
    if (ms < 50) return isDark ? 'text-emerald-400' : 'text-emerald-500';
    if (ms < 100) return isDark ? 'text-amber-400' : 'text-amber-500';
    return isDark ? 'text-rose-400' : 'text-rose-500';
  };

  const getLatencyIcon = (ms: number) => {
    if (ms < 50) return <SignalHigh size={16} />;
    if (ms < 100) return <Wifi size={16} />;
    return <SignalLow size={16} />;
  };

  return (
    <div className={`p-6 transition-colors duration-500`}>
      <header className="relative flex items-center justify-center mb-8 h-10">
        <button 
          onClick={onBack} 
          className={`absolute left-0 p-2 -ml-2 rounded-full transition-colors ${isDark ? 'hover:bg-slate-800' : 'hover:bg-slate-100'}`}
        >
          <ChevronLeft size={24} className={isDark ? 'text-white' : 'text-slate-900'} />
        </button>
        <h1 className={`text-xl font-bold tracking-tight transition-colors ${isDark ? 'text-white' : 'text-slate-900'}`}>服务器节点</h1>
      </header>

      <div className="relative mb-6">
        <div className={`absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors ${isDark ? 'text-slate-600' : 'text-slate-400'}`}>
          <Search size={18} />
        </div>
        <input 
          type="text" 
          placeholder="搜索服务器..."
          className={`w-full border rounded-2xl py-3 pl-12 pr-4 outline-none transition-all shadow-sm ${
            isDark 
            ? 'bg-slate-900 border-slate-800 text-white placeholder-slate-600 focus:border-emerald-500 focus:ring-emerald-500/10' 
            : 'bg-white border-slate-200 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500'
          }`}
        />
      </div>

      <div className="space-y-3">
        {nodes.map((node) => (
          <div 
            key={node.id} 
            className={`p-4 rounded-2xl border transition-all cursor-pointer active:scale-[0.98] ${
              isDark 
              ? 'bg-slate-900 border-slate-800 hover:border-emerald-900' 
              : 'bg-white border-slate-100 hover:border-emerald-200 shadow-sm'
            }`}
          >
            <div className="flex items-center justify-between">
              <div className="flex items-center space-x-4">
                <div className={`w-10 h-10 rounded-xl flex items-center justify-center transition-colors ${
                  isDark ? 'bg-slate-800 text-slate-500' : 'bg-slate-50 text-slate-400'
                }`}>
                  <Globe size={20} />
                </div>
                <div>
                  <div className="flex items-center space-x-2">
                    <span className={`font-bold transition-colors ${isDark ? 'text-slate-200' : 'text-slate-900'}`}>{node.name}</span>
                    <span className={`text-[10px] px-1.5 py-0.5 rounded font-bold transition-colors ${
                      isDark ? 'bg-slate-800 text-slate-500' : 'bg-slate-100 text-slate-500'
                    }`}>{node.type}</span>
                  </div>
                  <p className={`text-xs transition-colors ${isDark ? 'text-slate-500' : 'text-slate-400'}`}>{node.country}</p>
                </div>
              </div>
              <div className={`flex flex-col items-end ${getLatencyColor(node.latency)}`}>
                {getLatencyIcon(node.latency)}
                <span className="text-[10px] font-bold mt-0.5">{node.latency}ms</span>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default Nodes;
