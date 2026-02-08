
import React, { useState } from 'react';
import { UserData } from '../types';
import Logo from './Logo';
import { 
  ShieldCheck, 
  Activity, 
  MapPin, 
  Zap, 
  ChevronRight,
  Power
} from 'lucide-react';

interface Props {
  user: UserData;
  onOpenNodes: () => void;
  onOpenShop: () => void;
  theme: 'light' | 'dark';
}

const Dashboard: React.FC<Props> = ({ user, onOpenNodes, onOpenShop, theme }) => {
  const [isConnected, setIsConnected] = useState(false);
  const isDark = theme === 'dark';

  const formatData = (bytes: number) => {
    if (bytes === 0) return '0B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const usagePercent = (user.usedData / user.totalData) * 100;

  // Connection Card Styles logic
  const getCardStyles = () => {
    if (isConnected) {
      if (isDark) {
        return 'bg-[#0f172a] border-emerald-500/40 shadow-[0_0_50px_rgba(16,185,129,0.12)]';
      }
      return 'bg-emerald-500 border-emerald-400 shadow-2xl shadow-emerald-200/50';
    }
    return isDark 
      ? 'bg-slate-900 border-slate-800 shadow-2xl shadow-black/40' 
      : 'bg-white border-slate-100 shadow-xl shadow-slate-200/40';
  };

  return (
    <div className={`p-6 space-y-6 flex flex-col min-h-screen transition-colors duration-500`}>
      {/* Header */}
      <header className="flex justify-between items-center mb-2">
        <div className="flex items-center space-x-3">
          <div className="w-10 h-10">
            <Logo 
              classNameBody={isDark ? "stroke-white" : "stroke-slate-900"} 
              classNameRoad="stroke-emerald-500"
            />
          </div>
          <div>
            <h1 className={`text-2xl font-black tracking-tight transition-colors ${isDark ? 'text-white' : 'text-slate-900'}`}>大象网络</h1>
            <p className={`text-[10px] font-bold uppercase tracking-widest transition-colors ${isDark ? 'text-slate-500' : 'text-slate-400'}`}>Elephant Network</p>
          </div>
        </div>
        <div className={`p-2.5 rounded-2xl shadow-sm border transition-all ${isDark ? 'bg-slate-800 border-slate-700' : 'bg-white border-slate-100'}`}>
          <ShieldCheck className={`w-6 h-6 ${isDark ? 'text-emerald-400' : 'text-emerald-500'}`} />
        </div>
      </header>

      {/* User Status Card */}
      <div className={`rounded-3xl p-5 shadow-sm border transition-all flex items-center justify-between ${
        isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-100'
      }`}>
        <div className="flex items-center space-x-4">
          <div className={`w-12 h-12 rounded-2xl flex items-center justify-center font-bold text-xl shadow-inner transition-colors ${
            isDark ? 'bg-slate-800 text-emerald-400' : 'bg-emerald-100 text-emerald-600'
          }`}>
            {user.email[0].toUpperCase()}
          </div>
          <div>
            <p className={`font-bold text-sm transition-colors ${isDark ? 'text-slate-200' : 'text-slate-900'}`}>{user.email}</p>
            <div className="flex items-center space-x-2 mt-0.5">
              <span className={`text-[10px] px-2 py-0.5 rounded-full font-black uppercase tracking-wider transition-colors ${
                isDark ? 'bg-emerald-950/50 text-emerald-400' : 'bg-emerald-50 text-emerald-600'
              }`}>尊享会员</span>
              <span className={`text-[11px] font-medium transition-colors ${isDark ? 'text-slate-500' : 'text-slate-400'}`}>剩 {user.remainingDays} 天</span>
            </div>
          </div>
        </div>
        <div className="text-right">
          <p className={`text-[10px] font-bold uppercase tracking-widest transition-colors ${isDark ? 'text-slate-500' : 'text-slate-400'}`}>余额</p>
          <p className={`font-black transition-colors ${isDark ? 'text-white' : 'text-slate-900'}`}>¥{user.balance.toFixed(2)}</p>
        </div>
      </div>

      {/* Data Usage */}
      <div className={`rounded-3xl p-6 shadow-sm border transition-all ${
        isDark ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-100'
      }`}>
        <div className="flex justify-between items-center mb-4">
          <div className="flex items-center space-x-2 font-bold text-sm">
            <Activity size={16} className={isDark ? 'text-emerald-400' : 'text-emerald-500'} />
            <span className={isDark ? 'text-slate-200' : 'text-slate-800'}>流量使用统计</span>
          </div>
          <span className={`${isDark ? 'text-emerald-400' : 'text-emerald-500'} font-black text-sm`}>{usagePercent.toFixed(1)}%</span>
        </div>
        
        <div className={`h-2 rounded-full overflow-hidden mb-6 transition-colors ${isDark ? 'bg-slate-800' : 'bg-slate-50'}`}>
          <div 
            className={`h-full bg-emerald-500 transition-all duration-1000 ease-out rounded-full ${isDark ? 'shadow-[0_0_12px_rgba(16,185,129,0.4)]' : 'shadow-[0_0_8px_rgba(16,185,129,0.2)]'}`}
            style={{ width: `${Math.max(usagePercent, 2)}%` }}
          />
        </div>

        <div className="grid grid-cols-3 gap-2">
          <div className="text-center">
            <p className={`text-[10px] font-bold uppercase tracking-tight mb-1 ${isDark ? 'text-slate-500' : 'text-slate-400'}`}>已用</p>
            <p className={`font-black text-xs ${isDark ? 'text-slate-200' : 'text-slate-800'}`}>{formatData(user.usedData)}</p>
          </div>
          <div className={`text-center border-x transition-colors ${isDark ? 'border-slate-800' : 'border-slate-100'}`}>
            <p className={`text-[10px] font-bold uppercase tracking-tight mb-1 ${isDark ? 'text-slate-500' : 'text-slate-400'}`}>剩余</p>
            <p className={`font-black text-xs ${isDark ? 'text-emerald-400' : 'text-emerald-600'}`}>{formatData(user.totalData - user.usedData)}</p>
          </div>
          <div className="text-center">
            <p className={`text-[10px] font-bold uppercase tracking-tight mb-1 ${isDark ? 'text-slate-500' : 'text-slate-400'}`}>总量</p>
            <p className={`font-black text-xs ${isDark ? 'text-slate-200' : 'text-slate-800'}`}>{formatData(user.totalData)}</p>
          </div>
        </div>
      </div>

      {/* Main Connection Card */}
      <div className={`relative flex-1 min-h-[380px] overflow-hidden rounded-[56px] p-8 transition-all duration-700 ease-in-out border-2 ${getCardStyles()} flex flex-col justify-between items-center`}>
        
        {/* Subtle radial glow for connected dark mode */}
        {isConnected && isDark && (
          <div className="absolute inset-0 bg-[radial-gradient(circle_at_50%_40%,rgba(16,185,129,0.08),transparent_70%)] pointer-events-none" />
        )}

        {/* Decorative BG Icon */}
        <div className={`absolute top-0 right-0 p-8 pointer-events-none transition-all duration-700 ${isConnected ? (isDark ? 'opacity-[0.15] text-emerald-400' : 'opacity-10 text-white') : isDark ? 'opacity-[0.1] text-emerald-400' : 'opacity-[0.04] text-emerald-600'}`}>
          <Zap size={200} />
        </div>
        
        {/* Status Section */}
        <div className="relative z-10 w-full text-center space-y-4">
          <div className={`inline-flex items-center space-x-2 px-3 py-1.5 rounded-full border transition-all duration-500 ${
            isConnected 
            ? (isDark ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400 font-bold' : 'bg-white/10 border-white/20 text-white') 
            : (isDark ? 'bg-slate-800 border-slate-700 text-emerald-400 font-bold' : 'bg-emerald-50 border-emerald-100 text-emerald-600 font-bold')
          }`}>
             <div className={`w-1.5 h-1.5 rounded-full ${isConnected ? 'bg-emerald-300 animate-pulse shadow-[0_0_8px_rgba(52,211,153,0.5)]' : 'bg-emerald-400'}`} />
             <span className="text-[10px] uppercase tracking-widest">
               {isConnected ? 'Connection Secure' : 'Ready to Connect'}
             </span>
          </div>
          
          <div className="space-y-1">
            <h2 className={`text-2xl font-black tracking-tight transition-colors duration-500 ${isConnected ? (isDark ? 'text-white' : 'text-white') : (isDark ? 'text-white' : 'text-slate-900')}`}>
              {isConnected ? '正在加速' : '开启加速'}
            </h2>
            <p className={`text-[11px] font-medium max-w-[180px] mx-auto transition-colors duration-500 ${isConnected ? (isDark ? 'text-emerald-400/60' : 'text-white/70') : isDark ? 'text-slate-500' : 'text-slate-400'}`}>
              {isConnected ? '网络已加密，尽情畅游' : '一键开启全球高速无界网络'}
            </p>
          </div>
        </div>

        {/* Action Area */}
        <div className="relative z-10 flex flex-col items-center space-y-8 w-full pb-4">
          
          {/* Refined Primary Power Button */}
          <div className="relative">
            {/* Visual pulse for disconnected or connected states */}
            <div className={`absolute inset-0 rounded-full animate-pulse scale-125 -z-10 transition-colors ${
              isConnected 
                ? (isDark ? 'bg-emerald-500/20' : 'bg-white/20')
                : (isDark ? 'bg-emerald-500/10' : 'bg-emerald-500/5')
            }`} />

            <button 
              onClick={() => setIsConnected(!isConnected)}
              className={`group relative w-28 h-28 rounded-full flex items-center justify-center transition-all duration-500 ${
                isConnected 
                ? isDark 
                  ? 'bg-emerald-500 text-white scale-105 shadow-[0_0_40px_rgba(16,185,129,0.4)] active:scale-95' 
                  : 'bg-white text-emerald-500 scale-105 shadow-[0_15px_40px_rgba(0,0,0,0.1)] active:scale-95' 
                : isDark 
                  ? 'bg-slate-800 text-emerald-400 border-[3px] border-slate-700 shadow-[0_20px_40px_rgba(0,0,0,0.4)] hover:shadow-[0_25px_50px_rgba(16,185,129,0.3)] hover:border-slate-600 hover:scale-105 active:scale-90'
                  : 'bg-white text-slate-400 border-[3px] border-slate-50 shadow-[0_15px_30px_rgba(0,0,0,0.12)] hover:shadow-[0_20px_40px_rgba(16,185,129,0.2)] hover:border-emerald-50 hover:text-emerald-500 hover:scale-105 active:scale-90'
              }`}
            >
              {/* Outer decorative ring when off */}
              {!isConnected && (
                <div className={`absolute inset-[-10px] rounded-full border pointer-events-none transition-colors ${isDark ? 'border-slate-800 group-hover:border-slate-700' : 'border-slate-50 group-hover:border-emerald-50'}`} />
              )}
              
              {/* Animation rings when connected */}
              {isConnected && (
                <div className={`absolute inset-0 rounded-full border-2 animate-ping opacity-20 scale-125 ${isDark ? 'border-emerald-400' : 'border-white'}`} />
              )}
              
              <div className={`transition-all duration-300 ${isConnected ? 'scale-110' : 'scale-100'}`}>
                <Power size={48} strokeWidth={2.5} />
              </div>
            </button>
          </div>

          {/* Node Selection integrated into the card */}
          <button 
            onClick={onOpenNodes}
            className={`group flex items-center space-x-3 px-5 py-3.5 rounded-3xl transition-all border active:scale-95 ${
              isConnected 
              ? isDark 
                ? 'bg-slate-800/50 border-emerald-500/20 hover:bg-slate-800/80' 
                : 'bg-white/10 border-white/20 hover:bg-white/20' 
              : isDark 
                ? 'bg-slate-800 border-slate-700 shadow-lg hover:border-emerald-900' 
                : 'bg-white border-slate-100 shadow-md hover:border-emerald-200'
            }`}
          >
            <div className={`w-8 h-8 rounded-xl flex items-center justify-center transition-colors ${
              isConnected ? (isDark ? 'bg-emerald-500/20 text-emerald-400' : 'bg-white/20 text-white') : isDark ? 'bg-slate-700 text-emerald-400' : 'bg-emerald-50 text-emerald-500'
            }`}>
              <MapPin size={16} />
            </div>
            <div className="text-left">
              <p className={`text-[9px] font-black uppercase tracking-widest leading-none mb-0.5 ${isConnected ? (isDark ? 'text-emerald-400/40' : 'text-white/50') : isDark ? 'text-slate-500' : 'text-slate-400'}`}>当前节点</p>
              <span className={`text-xs font-bold block ${isConnected ? (isDark ? 'text-emerald-400' : 'text-white') : isDark ? 'text-slate-200' : 'text-slate-800'}`}>[ss] 新加坡高速 01</span>
            </div>
            <ChevronRight size={16} className={`transition-transform group-hover:translate-x-1 ${isConnected ? (isDark ? 'text-emerald-400/40' : 'text-white/40') : isDark ? 'text-slate-600' : 'text-slate-300'}`} />
          </button>
        </div>
      </div>
    </div>
  );
};

export default Dashboard;
